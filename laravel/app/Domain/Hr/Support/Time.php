<?php

declare(strict_types=1);

namespace App\Domain\Hr\Support;

use Carbon\Carbon;

/**
 * 排班/考勤时间工具：内部把 HH:MM 换算为「当日分钟数」比较，
 * 结束时间小于等于开始时间视为跨天班次（如 22:00-02:00）。
 */
final class Time
{
    public const MINUTES_PER_DAY = 1440;

    /**
     * 解析 HH:MM 或 HH:MM:SS 为当日分钟数（0-1439）。
     */
    public static function toMinutes(string $time): int
    {
        $raw = trim($time);

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $raw, $matches)) {
            throw new \InvalidArgumentException('时间格式非法（HH:MM）：'.$raw);
        }

        return ((int) $matches[1]) * 60 + ((int) $matches[2]);
    }

    /**
     * 统一格式化为 HH:MM。
     */
    public static function normalize(string $time): string
    {
        return self::fromMinutes(self::toMinutes($time));
    }

    public static function fromMinutes(int $minutes): string
    {
        $minutes = (($minutes % self::MINUTES_PER_DAY) + self::MINUTES_PER_DAY) % self::MINUTES_PER_DAY;

        return str_pad((string) intdiv($minutes, 60), 2, '0', STR_PAD_LEFT)
            .':'
            .str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 班次/工时长度（分钟），跨天时自动 +24h。
     */
    public static function duration(string $startTime, string $endTime): int
    {
        $start = self::toMinutes($startTime);
        $end = self::toMinutes($endTime);

        if ($start === $end) {
            throw new \InvalidArgumentException('开始时间与结束时间不能相同');
        }

        return $end > $start
            ? $end - $start
            : $end + self::MINUTES_PER_DAY - $start;
    }

    /**
     * 把「日期 + 起止时间」换算成可跨天比较的绝对分钟区间 [start, end)。
     *
     * @return array{0: int, 1: int}
     */
    public static function interval(string $date, string $startTime, string $endTime): array
    {
        $dayMinutes = intdiv(Carbon::parse($date)->startOfDay()->getTimestamp(), 60);
        $start = $dayMinutes + self::toMinutes($startTime);

        return [$start, $start + self::duration($startTime, $endTime)];
    }

    /**
     * 两个 [start, end) 区间是否重叠。
     *
     * @param  array{0: int, 1: int}  $left
     * @param  array{0: int, 1: int}  $right
     */
    public static function overlaps(array $left, array $right): bool
    {
        return $left[0] < $right[1] && $right[0] < $left[1];
    }
}
