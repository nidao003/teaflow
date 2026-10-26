<?php

declare(strict_types=1);

namespace App\Domain\Finance\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * 营业日区间工具：统一把日期归一为 Y-m-d，并给出当天 [start, end] 边界。
 */
final class Period
{
    public static function normalizeDate(string|\DateTimeInterface $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse($date)->toDateString();
    }

    public static function start(string|\DateTimeInterface $date): Carbon
    {
        return Carbon::parse(self::normalizeDate($date))->startOfDay();
    }

    public static function end(string|\DateTimeInterface $date): Carbon
    {
        return Carbon::parse(self::normalizeDate($date))->endOfDay();
    }

    /**
     * @return array{0: string, 1: string} [Y-m-d 00:00:00, Y-m-d 23:59:59]
     */
    public static function bounds(string|\DateTimeInterface $date): array
    {
        return [
            self::start($date)->toDateTimeString(),
            self::end($date)->toDateTimeString(),
        ];
    }

    /**
     * 两个日期之间（含首尾）的每一天。
     *
     * @return list<string>
     */
    public static function eachDate(string|\DateTimeInterface $from, string|\DateTimeInterface $to): array
    {
        $cursor = Carbon::parse(self::normalizeDate($from));
        $last = Carbon::parse(self::normalizeDate($to));
        $dates = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->copy()->addDay();
        }

        return $dates;
    }

    public static function isSameOrAfter(CarbonInterface $value, string $date): bool
    {
        return $value->greaterThanOrEqualTo(self::start($date));
    }
}
