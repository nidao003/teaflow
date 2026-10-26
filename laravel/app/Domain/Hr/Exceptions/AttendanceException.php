<?php

declare(strict_types=1);

namespace App\Domain\Hr\Exceptions;

/**
 * 考勤异常：重复打卡、未打卡、非法下班时间等。
 */
class AttendanceException extends HrException
{
    public static function alreadyClockedIn(int $staffId, string $date): self
    {
        return new self(sprintf('员工 #%d 在 %s 已签到，不可重复签到', $staffId, $date));
    }

    public static function notClockedIn(int $staffId, string $date): self
    {
        return new self(sprintf('员工 #%d 在 %s 尚未签到，不可签退', $staffId, $date));
    }

    public static function alreadyClockedOut(int $staffId, string $date): self
    {
        return new self(sprintf('员工 #%d 在 %s 已签退，不可重复签退', $staffId, $date));
    }

    public static function invalidClockOut(string $clockIn, string $clockOut): self
    {
        return new self(sprintf('签退时间非法：签到 %s，签退 %s', $clockIn, $clockOut));
    }

    public static function staffDisabled(int $staffId): self
    {
        return new self(sprintf('员工 #%d 已停用，不可打卡', $staffId));
    }
}
