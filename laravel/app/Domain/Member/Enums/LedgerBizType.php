<?php

declare(strict_types=1);

namespace App\Domain\Member\Enums;

use InvalidArgumentException;

/**
 * 台账业务类型。
 */
final class LedgerBizType
{
    /** 充值（积分入账复用该类型） */
    public const RECHARGE = 'recharge';

    /** 消费（积分抵扣复用该类型） */
    public const CONSUME = 'consume';

    /** 退款 */
    public const REFUND = 'refund';

    /** 人工调账 */
    public const ADJUST = 'adjust';

    /** 积分过期 */
    public const EXPIRE = 'expire';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::RECHARGE, self::CONSUME, self::REFUND, self::ADJUST, self::EXPIRE];
    }

    public static function assertValid(string $bizType): void
    {
        if (! in_array($bizType, self::all(), true)) {
            throw new InvalidArgumentException('非法台账业务类型：'.$bizType);
        }
    }
}
