<?php

declare(strict_types=1);

namespace App\Domain\Member\Enums;

/**
 * 通用启用/停用状态（会员、等级、积分规则共用）。
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
}
