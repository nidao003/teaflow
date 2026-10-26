<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Enums;

/**
 * 自提单状态机：pending → ready → picked；pending/ready → cancelled。
 */
enum PickupStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case PICKED = 'picked';
    case CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PENDING => [self::READY, self::CANCELLED],
            self::READY => [self::PICKED, self::CANCELLED],
            self::PICKED, self::CANCELLED => [],
        };
    }

    public function canTransitTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
