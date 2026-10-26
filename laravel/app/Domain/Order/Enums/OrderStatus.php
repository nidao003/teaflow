<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * 订单状态（对应 orders.order_status）。
 *
 * 允许的迁移见 OrderStateMachine::TRANSITIONS：
 * - pending_payment → paid → accepted → making → ready → completed
 * - pending_payment → cancelled
 * - paid → refunding → partially_refunded / refunded
 * - partially_refunded → refunding（支持累计多次部分退款）
 */
enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case ACCEPTED = 'accepted';
    case MAKING = 'making';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDING = 'refunding';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * 终态：不再发生订单状态迁移。
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::REFUNDED], true);
    }
}
