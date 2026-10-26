<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 排班状态（schedules.status）。
 */
final class ScheduleStatus
{
    public const SCHEDULED = 'scheduled';

    public const CANCELLED = 'cancelled';

    public const FINISHED = 'finished';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SCHEDULED, self::CANCELLED, self::FINISHED];
    }

    public static function assertValid(string $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('非法排班状态：'.$status);
        }
    }
}
