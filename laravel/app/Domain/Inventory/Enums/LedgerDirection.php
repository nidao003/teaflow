<?php

namespace App\Domain\Inventory\Enums;

/**
 * 库存流水出入方向。
 */
enum LedgerDirection: string
{
    case IN = 'in';
    case OUT = 'out';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function isIn(): bool
    {
        return $this === self::IN;
    }

    public function isOut(): bool
    {
        return $this === self::OUT;
    }
}
