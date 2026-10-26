<?php

declare(strict_types=1);

namespace App\Domain\Member\Enums;

use InvalidArgumentException;

/**
 * 会员账户类型（字符串常量，不使用原生枚举，便于数据库存储与序列化）。
 */
final class AccountType
{
    /** 储值/余额账户 */
    public const BALANCE = 'balance';

    /** 积分账户 */
    public const POINTS = 'points';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::BALANCE, self::POINTS];
    }

    public static function assertValid(string $accountType): void
    {
        if (! in_array($accountType, self::all(), true)) {
            throw new InvalidArgumentException('非法账户类型：'.$accountType);
        }
    }
}
