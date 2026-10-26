<?php

declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentChannel;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Enums\PayStatus;
use App\Domain\Order\Exceptions\DuplicateTransactionException;
use App\Domain\Order\Exceptions\OrderAlreadyPaidException;
use App\Domain\Order\Exceptions\OrderException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Exceptions\PaymentAmountMismatchException;
use App\Domain\Order\Exceptions\PaymentNotFoundException;
use App\Domain\Order\Exceptions\PaymentNotPayableException;
use App\Domain\Order\Exceptions\UnverifiedCallbackException;
use App\Domain\Order\Models\Payment;
use App\Domain\Order\Models\PaymentCallback;
use App\Domain\Order\Support\CallbackResult;
use App\Domain\Order\Support\Money;
use App\Domain\Order\Support\OrderRows;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * 支付服务：创建支付单、处理渠道异步回调、关闭过期支付单。
 *
 * 安全约束：
 * - 金额只取服务端订单（real_amount，兜底 total_amount），不接受客户端金额；
 * - 回调顺序为「先验签、后落库」，未验签不改变支付/订单状态；
 * - notify_id 与 idempotency_key 均做幂等，重复回调不会重复入账；
 * - 所有写入都在 DB 事务内，并对 order/payment 行加 lockForUpdate。
 */
class PaymentService
{
    public function __construct(
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * 创建支付单（按 order_number 读取服务端订单金额）。
     *
     * 同一 idempotency_key 重复调用返回同一支付单；同一订单换 key 会新建支付单（重试场景）。
     */
    public function createPayment(
        string $orderNumber,
        string $channel,
        string $idempotencyKey,
        ?int $storeId = null
    ): Payment {
        PaymentChannel::assertValid($channel);

        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('idempotency_key 不能为空');
        }

        return DB::transaction(function () use ($orderNumber, $channel, $idempotencyKey, $storeId): Payment {
            $order = OrderRows::findByOrderNumber($orderNumber, true);
            if ($order === null) {
                throw OrderNotFoundException::forOrderNumber($orderNumber);
            }

            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->order_number !== $orderNumber) {
                    throw OrderException::idempotencyKeyConflict($idempotencyKey, $orderNumber);
                }

                return $existing;
            }

            if ((string) ($order['pay_status'] ?? '') === PayStatus::COMPLETED->value) {
                throw OrderAlreadyPaidException::forOrderNumber($orderNumber);
            }

            $amountCents = $this->expectedAmountCents($order);
            if ($amountCents <= 0) {
                throw OrderException::invalidOrderAmount($orderNumber);
            }

            $payment = new Payment;
            $payment->order_number = $orderNumber;
            $payment->store_id = $storeId ?? $this->orderStoreId($order);
            $payment->channel = PaymentChannel::from($channel);
            $payment->out_trade_no = $this->generateOutTradeNo($orderNumber);
            $payment->amount = Money::fromCents($amountCents);
            $payment->status = PaymentStatus::CREATED;
            $payment->idempotency_key = $idempotencyKey;
            $payment->save();

            return $payment->refresh();
        });
    }

    /**
     * 标记支付单进入支付中（created → paying），已成功的支付单保持幂等返回。
     */
    public function markPaying(string $outTradeNo): Payment
    {
        return DB::transaction(function () use ($outTradeNo): Payment {
            $payment = Payment::query()->where('out_trade_no', $outTradeNo)->lockForUpdate()->first();
            if ($payment === null) {
                throw PaymentNotFoundException::forOutTradeNo($outTradeNo);
            }

            if ($payment->status === PaymentStatus::SUCCESS) {
                return $payment;
            }

            if (in_array($payment->status, [PaymentStatus::FAILED, PaymentStatus::CLOSED], true)) {
                throw PaymentNotPayableException::forOutTradeNo($outTradeNo, $payment->status);
            }

            if ($payment->status === PaymentStatus::CREATED) {
                $payment->status = PaymentStatus::PAYING;
                $payment->save();
            }

            return $payment->refresh();
        });
    }

    /**
     * 处理支付渠道异步回调。
     *
     * 顺序：验签结果校验 → notify_id 幂等 → 支付单定位 → 金额校验 → 支付成功落库。
     * 被拒绝的回调也会写一条 payment_callbacks 审计记录（verified 标记真实验签结果），
     * 但不会改变 payments / orders 的状态或金额。
     *
     * @param  array<string, mixed>  $payload  渠道原始回调报文
     */
    public function handleCallback(
        string $channel,
        string $notifyId,
        string $outTradeNo,
        string $transactionId,
        int|float|string $amount,
        bool $verified,
        array $payload = []
    ): PaymentCallback {
        PaymentChannel::assertValid($channel);

        $notifyId = trim($notifyId);
        if ($notifyId === '') {
            throw new InvalidArgumentException('notify_id 不能为空');
        }

        $result = DB::transaction(
            fn (): CallbackResult => $this->applyCallback(
                $channel,
                $notifyId,
                $outTradeNo,
                $transactionId,
                $amount,
                $verified,
                $payload
            )
        );

        if ($result->error === 'unverified') {
            throw UnverifiedCallbackException::forNotifyId($notifyId, $channel);
        }

        if ($result->error === 'amount_mismatch') {
            throw PaymentAmountMismatchException::forOutTradeNo(
                $outTradeNo,
                $result->detail['expected'] ?? '',
                $result->detail['actual'] ?? ''
            );
        }

        if ($result->error === 'not_payable') {
            throw PaymentNotPayableException::forOutTradeNo($outTradeNo, $result->detail['status'] ?? '');
        }

        return $result->callback;
    }

    /**
     * 关闭超时未支付的支付单（created / paying 且创建时间早于 now - minutes）。
     */
    public function closeExpired(int $minutes = 30, ?CarbonInterface $now = null): int
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('过期分钟数不能为负数');
        }

        $at = $now === null ? Carbon::now() : Carbon::instance($now);
        $cutoff = $at->copy()->subMinutes($minutes);

        return DB::transaction(function () use ($cutoff): int {
            $payments = Payment::query()
                ->whereIn('status', [PaymentStatus::CREATED->value, PaymentStatus::PAYING->value])
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $closed = 0;
            foreach ($payments as $payment) {
                $payment->status = PaymentStatus::CLOSED;
                $payment->save();
                $closed++;
            }

            return $closed;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCallback(
        string $channel,
        string $notifyId,
        string $outTradeNo,
        string $transactionId,
        int|float|string $amount,
        bool $verified,
        array $payload
    ): CallbackResult {
        $callback = PaymentCallback::query()->where('notify_id', $notifyId)->lockForUpdate()->first();

        // notify_id 已处理完成：直接幂等返回，不重复入账。
        if ($callback !== null && $callback->processed_at !== null) {
            return new CallbackResult($callback);
        }

        $payment = Payment::query()->where('out_trade_no', $outTradeNo)->lockForUpdate()->first();
        if ($payment === null) {
            throw PaymentNotFoundException::forOutTradeNo($outTradeNo);
        }

        if ($payment->channel->value !== $channel) {
            throw OrderException::channelMismatch($outTradeNo, $payment->channel->value, $channel);
        }

        $callback ??= new PaymentCallback;
        $callback->channel = $channel;
        $callback->notify_id = $notifyId;
        $callback->payload = $payload;
        $callback->verified = $verified;

        // 先验签：未通过的回调只留痕，不落支付结果。
        if (! $verified) {
            $callback->processed_at = null;
            $callback->save();

            return new CallbackResult($callback, 'unverified');
        }

        $expectedCents = Money::toCents((string) $payment->amount);
        $actualCents = Money::toCents($amount);

        // 金额以支付单（即服务端订单）为准，不一致直接拒绝。
        if ($expectedCents !== $actualCents) {
            $callback->processed_at = now();
            $callback->save();

            return new CallbackResult($callback, 'amount_mismatch', [
                'expected' => Money::fromCents($expectedCents),
                'actual' => Money::fromCents($actualCents),
            ]);
        }

        // 支付单已成功：渠道重复通知（可能换了 notify_id）只补记回调，不再入账。
        if ($payment->status === PaymentStatus::SUCCESS) {
            $callback->processed_at = now();
            $callback->save();

            return new CallbackResult($callback);
        }

        if (in_array($payment->status, [PaymentStatus::CLOSED, PaymentStatus::FAILED], true)) {
            $callback->processed_at = now();
            $callback->save();

            return new CallbackResult($callback, 'not_payable', ['status' => $payment->status->value]);
        }

        if (Payment::query()->where('transaction_id', $transactionId)->where('id', '<>', $payment->getKey())->exists()) {
            throw DuplicateTransactionException::forTransactionId($transactionId);
        }

        $payment->status = PaymentStatus::SUCCESS;
        $payment->transaction_id = $transactionId;
        $payment->paid_at = now();
        $payment->raw_notify = $payload;
        $payment->save();

        $this->applyPaidOrder($payment);

        $callback->processed_at = now();
        $callback->save();

        return new CallbackResult($callback->refresh());
    }

    /**
     * 支付成功后回写现有 orders 表（pay_status / paid_amount / real_amount / order_status）。
     */
    private function applyPaidOrder(Payment $payment): void
    {
        $order = OrderRows::findByOrderNumber($payment->order_number, true);
        if ($order === null) {
            throw OrderNotFoundException::forOrderNumber($payment->order_number);
        }

        $paidCents = Money::toCents((string) $payment->amount);
        $refundedCents = Money::toCents((string) ($order['refunded_amount'] ?? '0'));
        $realCents = max(0, $paidCents - $refundedCents);
        $currentStatus = (string) ($order['order_status'] ?? '');

        DB::table('orders')->where('order_number', $payment->order_number)->update([
            'pay_status' => PayStatus::COMPLETED->value,
            'paid_amount' => Money::fromCents($paidCents),
            'real_amount' => Money::fromCents($realCents),
            'paid_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'payment_id' => $payment->getKey(),
            'out_trade_no' => $payment->out_trade_no,
            'transaction_id' => $payment->transaction_id,
            'channel' => $payment->channel->value,
            'amount' => Money::fromCents($paidCents),
        ];

        if ($this->stateMachine->canTransit($currentStatus, OrderStatus::PAID)) {
            $this->stateMachine->transition($payment->order_number, OrderStatus::PAID, 'payment_succeeded', null, $payload);

            return;
        }

        // 历史订单状态（如上游 pending）或已支付订单：只记审计事件，不改 order_status。
        $this->stateMachine->record($payment->order_number, $currentStatus, $currentStatus, 'payment_succeeded', null, $payload);
    }

    /**
     * 应从订单收取的金额（分）：优先 real_amount，其次 total_amount。
     *
     * @param  array<string, mixed>  $order
     */
    private function expectedAmountCents(array $order): int
    {
        $realCents = Money::toCents((string) ($order['real_amount'] ?? '0'));
        if ($realCents > 0) {
            return $realCents;
        }

        return Money::toCents((string) ($order['total_amount'] ?? '0'));
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function orderStoreId(array $order): ?int
    {
        $storeId = $order['store_id'] ?? null;
        if ($storeId === null || (int) $storeId === 0) {
            return null;
        }

        return (int) $storeId;
    }

    private function generateOutTradeNo(string $orderNumber): string
    {
        return 'P'.$orderNumber.now()->format('YmdHis').strtoupper(Str::random(6));
    }
}
