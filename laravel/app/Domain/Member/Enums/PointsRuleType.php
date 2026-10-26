<?php

declare(strict_types=1);

namespace App\Domain\Member\Enums;

use InvalidArgumentException;

/**
 * 积分规则类型。
 *
 * ratio 语义随 rule_type 变化：
 * - earn：每 1 元消费获得的积分数量；
 * - expire：积分有效期（天）。
 */
final class PointsRuleType
{
    public const EARN = 'earn';

    public const EXPIRE = 'expire';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::EARN, self::EXPIRE];
    }

    public static function assertValid(string $ruleType): void
    {
        if (! in_array($ruleType, self::all(), true)) {
            throw new InvalidArgumentException('非法积分规则类型：'.$ruleType);
        }
    }
}
