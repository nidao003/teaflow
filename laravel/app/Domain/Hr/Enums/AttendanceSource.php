<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 考勤数据来源（attendance.source）。
 */
final class AttendanceSource
{
    public const MANUAL = 'manual';

    public const DEVICE = 'device';

    public const IMPORT = 'import';

    public const SYSTEM = 'system';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::MANUAL, self::DEVICE, self::IMPORT, self::SYSTEM];
    }

    public static function assertValid(string $source): void
    {
        if (! in_array($source, self::all(), true)) {
            throw new InvalidArgumentException('非法考勤来源：'.$source);
        }
    }
}
