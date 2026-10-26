<?php

declare(strict_types=1);

namespace App\Domain\Member\Enums;

use InvalidArgumentException;

/**
 * 台账方向：in 入账（加余额），out 出账（减余额）。
 */
final class LedgerDirection
{
    public const IN = 'in';

    public const OUT = 'out';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::IN, self::OUT];
    }

    public static function assertValid(string $direction): void
    {
        if (! in_array($direction, self::all(), true)) {
            throw new InvalidArgumentException('非法台账方向：'.$direction);
        }
    }
}
