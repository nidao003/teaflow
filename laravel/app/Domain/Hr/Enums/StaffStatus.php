<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 员工在职状态（staff.status）。
 */
final class StaffStatus
{
    public const DISABLED = 0;

    public const ACTIVE = 1;

    /** @return list<int> */
    public static function all(): array
    {
        return [self::DISABLED, self::ACTIVE];
    }

    public static function assertValid(int $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('非法员工状态：'.$status);
        }
    }
}
