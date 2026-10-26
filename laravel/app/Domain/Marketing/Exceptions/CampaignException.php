<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Exceptions;

/**
 * 活动配置/状态/规则异常。
 */
class CampaignException extends MarketingException
{
    public static function invalidRules(string $type, string $reason): self
    {
        return new self(sprintf('活动类型 %s 的规则配置非法：%s', $type, $reason));
    }

    public static function invalidStatusTransition(int $campaignId, string $from, string $to): self
    {
        return new self(sprintf('活动 #%d 状态不允许从 %s 流转到 %s', $campaignId, $from, $to));
    }

    public static function invalidWindow(string $startAt, string $endAt): self
    {
        return new self(sprintf('活动时间窗非法：%s ~ %s', $startAt, $endAt));
    }

    public static function storeRequired(): self
    {
        return new self('门店范围活动必须指定 store_id');
    }
}
