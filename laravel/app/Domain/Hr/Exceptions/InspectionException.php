<?php

declare(strict_types=1);

namespace App\Domain\Hr\Exceptions;

/**
 * 巡检异常：缺检查项、必填项未评分、重复提交、状态流转非法等。
 */
class InspectionException extends HrException
{
    public static function emptyItems(int $inspectionId): self
    {
        return new self(sprintf('巡检单 #%d 没有检查项，无法提交评分', $inspectionId));
    }

    public static function missingResult(int $inspectionId, string $item): self
    {
        return new self(sprintf('巡检单 #%d 的检查项「%s」未评分，不能提交', $inspectionId, $item));
    }

    public static function alreadySubmitted(int $inspectionId, string $status): self
    {
        return new self(sprintf('巡检单 #%d 已提交（状态 %s），不可重复提交', $inspectionId, $status));
    }

    public static function invalidTransition(int $inspectionId, string $from, string $to): self
    {
        return new self(sprintf('巡检单 #%d 状态不允许从 %s 流转到 %s', $inspectionId, $from, $to));
    }
}
