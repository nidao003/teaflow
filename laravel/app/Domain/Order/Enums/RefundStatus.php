<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * 退款单状态（refunds.status）。
 */
enum RefundStatus: string
{
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED], true);
    }
}
