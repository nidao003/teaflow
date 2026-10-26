<?php

declare(strict_types=1);

namespace App\Domain\Hr\Support;

use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * 日期工具：统一使用 Y-m-d 字符串入库与比较。
 */
final class Dates
{
    public static function normalize(string $date): string
    {
        $raw = trim($date);

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $raw);
        } catch (Throwable) {
            throw new InvalidArgumentException('日期格式非法（Y-m-d）：'.$raw);
        }

        if ($parsed === null || $parsed->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException('日期格式非法（Y-m-d）：'.$raw);
        }

        return $parsed->toDateString();
    }

    public static function normalizeOrNull(?string $date): ?string
    {
        return $date === null ? null : self::normalize($date);
    }

    /**
     * 日期偏移（天）。
     */
    public static function shift(string $date, int $days): string
    {
        return Carbon::parse(self::normalize($date))->addDays($days)->toDateString();
    }
}
