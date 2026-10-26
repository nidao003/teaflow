<?php

declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Enums\PayStatus;
use App\Domain\Order\Enums\RefundStatus;
use App\Domain\Order\Exceptions\DuplicateRefundNoException;
use App\Domain\Order\Exceptions\InvalidRefundStatusException;
use App\Domain\Order\Exceptions\OrderException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Exceptions\OrderNotPaidException;
use App\Domain\Order\Exceptions\RefundExceedsPaidAmountException;
use App\Domain\Order\Exceptions\RefundNotFoundException;
use App\Domain\Order\Models\Payment;
use App\Domain\Order\Models\Refund;
use App\Domain\Order\Support\Money;
use App\Domain\Order\Support\OrderRows;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 退款服务：申请（占用额度）→ 审批 → 执行（回写订单金额与状态机）。
 *
 * 约束：
 * - 退款单号 refund_no 唯一且幂等；
 * - 累计退款额度 = orders.refunded_amount + 处理中的退款单金额，不得超过实付金额；
 * - 执行退款成功后按分运算回写 orders.refunded_amount / real_amount，并迁移订单状态。
 */
class RefundService
{
    public function __construct(
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * 申请退款（created 状态，占用退款额度）。
     *
     * 同一 refund_no 重复申请幂等返回已有退款单；同一订单重复申请必须换 refund_no。
     */
    public function requestRefund(
        string $orderNumber,
        int|float|string $amount,
        string $refundNo,
        ?string $reason = null,
        ?int $operatorId = null,
        ?int $paymentId = null
    ): Refund {
        $amountCents = Money::toCents($amount);
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('退款金额必须大于 0');
        }

        $refundNo = trim($refundNo);
        if ($refundNo === '') {
            throw new InvalidArgumentException('退款单号不能为空');
        }

        return DB::transaction(function () use ($orderNumber, $amountCents, $refundNo, $reason, $operatorId, $paymentId): Refund {
            $order = OrderRows::findByOrderNumber($orderNumber, true);
            if ($order === null) {
                throw OrderNotFoundException::forOrderNumber($orderNumber);
            }

            $existing = Refund::query()->where('refund_no', $refundNo)->first();
            if ($existing !== null) {
                if ($existing->order_number !== $orderNumber) {
                    throw DuplicateRefundNoException::forRefundNo($refundNo, $orderNumber);
                }

                return $existing;
            }

            if ((string) ($order['pay_status'] ?? '') !== PayStatus::COMPLETED->value) {
                throw OrderNotPaidException::forOrderNumber($orderNumber);
            }

            $paidCents = $this->paidCents($order);
            if ($paidCents <= 0) {
                throw OrderException::invalidOrderAmount($orderNumber);
            }

            $refundedCents = Money::toCents((string) ($order['refunded_amount'] ?? '0'));
            $pendingCents = $this->pendingRefundCents($orderNumber);

            if ($refundedCents + $pendingCents + $amountCents > $paidCents) {
                throw RefundExceedsPaidAmountException::forOrder(
                    $orderNumber,
                    Money::fromCents($paidCents),
                    Money::fromCents($refundedCents + $pendingCents),
                    Money::fromCents($amountCents)
                );
            }

            $refund = new Refund;
            $refund->order_number = $orderNumber;
            $refund->payment_id = $paymentId ?? $this->latestSuccessfulPaymentId($orderNumber);
            $refund->refund_no = $refundNo;
            $refund->amount = Money::fromCents($amountCents);
            $refund->reason = $reason;
            $refund->status = RefundStatus::CREATED;
            $refund->operator_id = $operatorId;
            $refund->save();

            $currentStatus = (string) ($order['order_status'] ?? '');
            $payload = [
                'refund_no' => $refundNo,
                'amount' => Money::fromCents($amountCents),
            ];

            if ($this->stateMachine->canTransit($currentStatus, OrderStatus::REFUNDING)) {
                $this->stateMachine->transition($orderNumber, OrderStatus::REFUNDING, 'refund_requested', $operatorId, $payload);
            } else {
                $this->stateMachine->record($orderNumber, $currentStatus, $currentStatus, 'refund_requested', $operatorId, $payload);
            }

            return $refund->refresh();
        });
    }

    /**
     * 审批退款单（created → processing）。
     */
    public function approve(string $refundNo, int $approvedBy, ?int $operatorId = null): Refund
    {
        return DB::transaction(function () use ($refundNo, $approvedBy, $operatorId): Refund {
            $refund = Refund::query()->where('refund_no', $refundNo)->lockForUpdate()->first();
            if ($refund === null) {
                throw RefundNotFoundException::forRefundNo($refundNo);
            }

            if ($refund->status === RefundStatus::SUCCESS) {
                return $refund;
            }

            if ($refund->status !== RefundStatus::CREATED) {
                throw InvalidRefundStatusException::expected($refundNo, RefundStatus::CREATED, $refund->status);
            }

            $refund->status = RefundStatus::PROCESSING;
            $refund->approved_by = $approvedBy;
            $refund->save();

            $currentStatus = (string) (OrderRows::status($refund->order_number) ?? '');
            $this->stateMachine->record($refund->order_number, $currentStatus, $currentStatus, 'refund_approved', $operatorId ?? $approvedBy, [
                'refund_no' => $refundNo,
                'approved_by' => $approvedBy,
            ]);

            return $refund->refresh();
        });
    }

    /**
     * 执行退款（processing → success）：回写订单已退/剩余金额并迁移订单状态。
     *
     * 同一退款单重复执行幂等返回，不会重复累加订单退款金额。
     */
    public function execute(string $refundNo, string $channelRefundId, ?int $operatorId = null): Refund
    {
        return DB::transaction(function () use ($refundNo, $channelRefundId, $operatorId): Refund {
            $refund = Refund::query()->where('refund_no', $refundNo)->lockForUpdate()->first();
            if ($refund === null) {
                throw RefundNotFoundException::forRefundNo($refundNo);
            }

            if ($refund->status === RefundStatus::SUCCESS) {
                return $refund;
            }

            if ($refund->status !== RefundStatus::PROCESSING) {
                throw InvalidRefundStatusException::expected($refundNo, RefundStatus::PROCESSING, $refund->status);
            }

            $order = OrderRows::findByOrderNumber($refund->order_number, true);
            if ($order === null) {
                throw OrderNotFoundException::forOrderNumber($refund->order_number);
            }

            $paidCents = $this->paidCents($order);
            $refundedCents = Money::toCents((string) ($order['refunded_amount'] ?? '0'));
            $refundCents = Money::toCents((string) $refund->amount);
            $newRefundedCents = $refundedCents + $refundCents;

            if ($newRefundedCents > $paidCents) {
                throw RefundExceedsPaidAmountException::forOrder(
                    $refund->order_number,
                    Money::fromCents($paidCents),
                    Money::fromCents($refundedCents),
                    Money::fromCents($refundCents)
                );
            }

            $realCents = max(0, $paidCents - $newRefundedCents);

            DB::table('orders')->where('order_number', $refund->order_number)->update([
                'refunded_amount' => Money::fromCents($newRefundedCents),
                'real_amount' => Money::fromCents($realCents),
                'updated_at' => now(),
            ]);

            $refund->status = RefundStatus::SUCCESS;
            $refund->refunded_at = now();
            $refund->channel_refund_id = $channelRefundId;
            $refund->save();

            $targetStatus = $newRefundedCents >= $paidCents
                ? OrderStatus::REFUNDED
                : OrderStatus::PARTIALLY_REFUNDED;
            $currentStatus = (string) ($order['order_status'] ?? '');
            $payload = [
                'refund_no' => $refundNo,
                'amount' => Money::fromCents($refundCents),
                'refunded_amount' => Money::fromCents($newRefundedCents),
                'real_amount' => Money::fromCents($realCents),
            ];

            if ($this->stateMachine->canTransit($currentStatus, $targetStatus)) {
                $this->stateMachine->transition($refund->order_number, $targetStatus, 'refund_succeeded', $operatorId, $payload);
            } else {
                $this->stateMachine->record($refund->order_number, $currentStatus, $currentStatus, 'refund_succeeded', $operatorId, $payload);
            }

            return $refund->refresh();
        });
    }

    /**
     * 订单实付金额（分）：优先 paid_amount，历史数据兜底 total_amount。
     *
     * @param  array<string, mixed>  $order
     */
    private function paidCents(array $order): int
    {
        $paidCents = Money::toCents((string) ($order['paid_amount'] ?? '0'));

        return $paidCents > 0 ? $paidCents : Money::toCents((string) ($order['total_amount'] ?? '0'));
    }

    /**
     * 处理中（created/processing）退款金额合计（分），用于额度预占。
     */
    private function pendingRefundCents(string $orderNumber): int
    {
        $sum = Refund::query()
            ->where('order_number', $orderNumber)
            ->whereIn('status', [RefundStatus::CREATED->value, RefundStatus::PROCESSING->value])
            ->sum('amount');

        return Money::toCents((string) $sum);
    }

    private function latestSuccessfulPaymentId(string $orderNumber): ?int
    {
        $paymentId = Payment::query()
            ->where('order_number', $orderNumber)
            ->where('status', PaymentStatus::SUCCESS->value)
            ->orderByDesc('id')
            ->value('id');

        return $paymentId === null ? null : (int) $paymentId;
    }
}
