<?php

namespace App\Domain\Inventory\Enums;

/**
 * 盘点单状态机：counting → counted → completed / cancelled。
 */
enum StocktakeStatus: string
{
    case COUNTING = 'counting';
    case COUNTED = 'counted';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return self[]
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::COUNTING => [self::COUNTED, self::CANCELLED],
            self::COUNTED => [self::COUNTING, self::COMPLETED, self::CANCELLED],
            self::COMPLETED, self::CANCELLED => [],
        };
    }

    public function canTransitTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
