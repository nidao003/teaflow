<?php

declare(strict_types=1);

namespace App\Domain\Order\Support;

use InvalidArgumentException;

/**
 * 订单域金额换算工具。
 *
 * 数据库统一使用 decimal(10,2) 存储；为避免浮点误差，所有金额加减先在「分」
 * 维度（整数）完成，再格式化回两位小数字符串。
 */
final class Money
{
    public const SCALE = 2;

    /**
     * 将元金额转换为最小单位（分）。
     */
    public static function toCents(int|float|string $amount): int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }

        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('金额必须是有效数字');
            }

            return (int) round($amount * 100);
        }

        $value = trim($amount);

        if ($value === '' || ! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('金额格式非法：'.$value);
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');

        $cents = ((int) $whole) * 100 + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    /**
     * 将最小单位（分）格式化为两位小数字符串。
     */
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
