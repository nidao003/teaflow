<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Support;

use InvalidArgumentException;

/**
 * 营销域金额工具：数据库统一 decimal(10,2)，运算换算到「分」整数，避免浮点误差。
 */
final class Money
{
    public static function toCents(int|float|string $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('金额必须是有效数字');
            }

            return (int) round($value * 100);
        }

        $raw = trim($value);

        if ($raw === '' || ! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('金额格式非法：'.$raw);
        }

        $negative = str_starts_with($raw, '-');
        $unsigned = ltrim($raw, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');

        $cents = ((int) $whole) * 100 + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $abs = abs($cents);

        return ($negative ? '-' : '')
            .intdiv($abs, 100)
            .'.'
            .str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
