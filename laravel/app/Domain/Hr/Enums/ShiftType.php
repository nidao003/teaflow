<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 班次类型（schedules.shift_type）。
 */
final class ShiftType
{
    public const MORNING = 'morning';

    public const AFTERNOON = 'afternoon';

    public const EVENING = 'evening';

    public const FULL = 'full';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::MORNING, self::AFTERNOON, self::EVENING, self::FULL];
    }

    public static function assertValid(string $shiftType): void
    {
        if (! in_array($shiftType, self::all(), true)) {
            throw new InvalidArgumentException('非法班次类型：'.$shiftType);
        }
    }
}
