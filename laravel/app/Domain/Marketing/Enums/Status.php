<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Enums;

use InvalidArgumentException;

/**
 * 通用启用/停用状态（会员价规则、会员标签共用）。
 */
final class Status
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
            throw new InvalidArgumentException('非法状态：'.$status);
        }
    }
}
