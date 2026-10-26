<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Enums;

use InvalidArgumentException;

/**
 * 活动适用范围（campaigns.scope）。
 */
final class CampaignScope
{
    /** 全部门店 */
    public const ALL = 'all';

    /** 指定门店 */
    public const STORE = 'store';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ALL, self::STORE];
    }

    public static function assertValid(string $scope): void
    {
        if (! in_array($scope, self::all(), true)) {
            throw new InvalidArgumentException('非法活动范围：'.$scope);
        }
    }
}
