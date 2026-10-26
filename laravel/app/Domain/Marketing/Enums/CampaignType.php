<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Enums;

use InvalidArgumentException;

/**
 * 活动类型（campaigns.type）。
 */
final class CampaignType
{
    /** 满减 */
    public const FULL_REDUCTION = 'full_reduction';

    /** 折扣（rules_json.percent 为实付百分比，90 表示 9 折） */
    public const PERCENT = 'percent';

    /** 第二件半价 */
    public const SECOND_HALF = 'second_half';

    /** 赠品 */
    public const GIFT = 'gift';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::FULL_REDUCTION, self::PERCENT, self::SECOND_HALF, self::GIFT];
    }

    public static function assertValid(string $type): void
    {
        if (! in_array($type, self::all(), true)) {
            throw new InvalidArgumentException('非法活动类型：'.$type);
        }
    }
}
