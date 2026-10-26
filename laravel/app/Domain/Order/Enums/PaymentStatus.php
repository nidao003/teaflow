<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * 支付单状态（payments.status）。
 *
 * created → paying → success / failed / closed，success 后不再变更。
 */
enum PaymentStatus: string
{
    case CREATED = 'created';
    case PAYING = 'paying';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CLOSED = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED, self::CLOSED], true);
    }
}
