<?php

declare(strict_types=1);

namespace App\Domain\Finance\Support;

/**
 * 金额/比率工具：金额统一保留两位小数，毛利率按百分比两位小数。
 */
final class Amount
{
    public static function round2(mixed $value): float
    {
        return round((float) $value, 2);
    }

    public static function round3(mixed $value): float
    {
        return round((float) $value, 3);
    }

    /**
     * 毛利率（百分比）：revenue <= 0 时返回 0.0，避免除零。
     */
    public static function marginPercent(float $grossProfit, float $revenue): float
    {
        if ($revenue <= 0.0) {
            return 0.0;
        }

        return self::round2($grossProfit / $revenue * 100);
    }
}
