<?php

declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\IllegalOrderTransitionException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Models\OrderEvent;
use App\Domain\Order\Support\OrderRows;
use App\Domain\Order\Support\TransitionResult;
use Illuminate\Support\Facades\DB;

/**
 * 订单状态机。
 *
 * 允许的迁移：
 * - pending_payment → paid → accepted → making → ready → completed
 * - pending_payment → cancelled
 * - paid → refunding → partially_refunded / refunded
 * - partially_refunded → refunding（部分退款后可再次发起，支持累计多次退款）
 *
 * 迁移通过 DB::table 更新现有 orders.order_status，并写 order_events 审计。
 * 非法迁移会先提交一条 illegal_transition 事件，再抛出异常，保证非法尝试可追溯。
 */
class OrderStateMachine
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        OrderStatus::PENDING_PAYMENT->value => [
            OrderStatus::PAID->value,
            OrderStatus::CANCELLED->value,
        ],
        OrderStatus::PAID->value => [
            OrderStatus::ACCEPTED->value,
            OrderStatus::REFUNDING->value,
        ],
        OrderStatus::ACCEPTED->value => [OrderStatus::MAKING->value],
        OrderStatus::MAKING->value => [OrderStatus::READY->value],
        OrderStatus::READY->value => [OrderStatus::COMPLETED->value],
        OrderStatus::REFUNDING->value => [
            OrderStatus::PARTIALLY_REFUNDED->value,
            OrderStatus::REFUNDED->value,
        ],
        OrderStatus::PARTIALLY_REFUNDED->value => [OrderStatus::REFUNDING->value],
        OrderStatus::COMPLETED->value => [],
        OrderStatus::CANCELLED->value => [],
        OrderStatus::REFUNDED->value => [],
    ];

    /**
     * @return list<string>
     */
    public function allowedTransitions(string|OrderStatus $from): array
    {
        return self::TRANSITIONS[$this->statusValue($from)] ?? [];
    }

    public function canTransit(string|OrderStatus $from, string|OrderStatus $to): bool
    {
        return in_array($this->statusValue($to), $this->allowedTransitions($from), true);
    }

    public function assertCanTransit(string|OrderStatus $from, string|OrderStatus $to): void
    {
        $fromValue = $this->statusValue($from);
        $toValue = $this->statusValue($to);

        if (! in_array($toValue, $this->allowedTransitions($fromValue), true)) {
            throw IllegalOrderTransitionException::notAllowed(null, $fromValue, $toValue);
        }
    }

    /**
     * 执行一次状态迁移：锁订单行 → 校验迁移 → 更新 orders.order_status → 写 order_events。
     *
     * @param  array<string, mixed>  $payload
     */
    public function transition(
        string $orderNumber,
        string|OrderStatus $to,
        string $event,
        ?int $operatorId = null,
        array $payload = []
    ): OrderEvent {
        $result = DB::transaction(
            fn (): TransitionResult => $this->applyTransition($orderNumber, $to, $event, $operatorId, $payload)
        );

        if ($result->illegal) {
            throw IllegalOrderTransitionException::notAllowed($orderNumber, $result->from, $result->to);
        }

        return $result->event;
    }

    /**
     * 追加一条 order_events 审计事件（不校验状态迁移）。
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $orderNumber,
        ?string $fromStatus,
        string $toStatus,
        string $event,
        ?int $operatorId = null,
        array $payload = []
    ): OrderEvent {
        $row = new OrderEvent;
        $row->order_number = $orderNumber;
        $row->from_status = $fromStatus;
        $row->to_status = $toStatus;
        $row->event = $event;
        $row->operator_id = $operatorId;
        $row->payload = $payload === [] ? null : $payload;
        $row->save();

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyTransition(
        string $orderNumber,
        string|OrderStatus $to,
        string $event,
        ?int $operatorId,
        array $payload
    ): TransitionResult {
        $order = OrderRows::findByOrderNumber($orderNumber, true);

        if ($order === null) {
            throw OrderNotFoundException::forOrderNumber($orderNumber);
        }

        $fromStatus = (string) ($order['order_status'] ?? '');
        $toStatus = $this->statusValue($to);

        if (! in_array($toStatus, $this->allowedTransitions($fromStatus), true)) {
            $row = $this->record(
                $orderNumber,
                $fromStatus,
                $toStatus,
                'illegal_transition',
                $operatorId,
                $payload + ['attempted_event' => $event]
            );

            return new TransitionResult(true, $fromStatus, $toStatus, $row);
        }

        DB::table('orders')->where('order_number', $orderNumber)->update([
            'order_status' => $toStatus,
            'updated_at' => now(),
        ]);

        $row = $this->record($orderNumber, $fromStatus, $toStatus, $event, $operatorId, $payload);

        return new TransitionResult(false, $fromStatus, $toStatus, $row);
    }

    private function statusValue(string|OrderStatus $status): string
    {
        return $status instanceof OrderStatus ? $status->value : $status;
    }
}
