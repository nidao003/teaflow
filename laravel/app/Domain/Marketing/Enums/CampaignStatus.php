<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Enums;

use InvalidArgumentException;

/**
 * 活动状态（campaigns.status）。
 *
 * 流转：draft（草稿）→ active（生效）/ disabled（停用）。
 */
final class CampaignStatus
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const DISABLED = 'disabled';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::ACTIVE, self::DISABLED];
    }

    public static function assertValid(string $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('非法活动状态：'.$status);
        }
    }
}
