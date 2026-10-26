<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 考勤状态（attendance.status）。
 */
final class AttendanceStatus
{
    public const PRESENT = 'present';

    public const LATE = 'late';

    public const EARLY_LEAVE = 'early_leave';

    public const ABSENT = 'absent';

    public const LEAVE = 'leave';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PRESENT, self::LATE, self::EARLY_LEAVE, self::ABSENT, self::LEAVE];
    }

    public static function assertValid(string $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('非法考勤状态：'.$status);
        }
    }

    /**
     * 异常考勤状态（用于异常清单）。
     *
     * @return list<string>
     */
    public static function exceptionStatuses(): array
    {
        return [self::LATE, self::EARLY_LEAVE, self::ABSENT];
    }
}
