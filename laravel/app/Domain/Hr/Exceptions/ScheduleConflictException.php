<?php

declare(strict_types=1);

namespace App\Domain\Hr\Exceptions;

/**
 * 排班冲突：同一员工同一天存在重叠班次，或同班次重复排班。
 */
class ScheduleConflictException extends HrException
{
    public static function overlap(string $date, string $startTime, string $endTime, int $existingScheduleId): self
    {
        return new self(sprintf(
            '排班冲突：%s %s-%s 与已有排班 #%d 时间重叠',
            $date,
            $startTime,
            $endTime,
            $existingScheduleId
        ));
    }

    public static function duplicateShift(int $staffId, string $date, string $shiftType): self
    {
        return new self(sprintf('排班冲突：员工 #%d 在 %s 的 %s 班次已存在', $staffId, $date, $shiftType));
    }

    public static function invalidRange(string $startTime, string $endTime): self
    {
        return new self(sprintf('排班时间非法：%s-%s', $startTime, $endTime));
    }
}
