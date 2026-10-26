<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * 订单支付状态（对应现有 orders.pay_status，沿用上游约定取值）。
 */
enum PayStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
