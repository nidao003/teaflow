<?php

declare(strict_types=1);

namespace App\Domain\Hr\Exceptions;

/**
 * 客诉异常：状态流转非法等。
 */
class ComplaintException extends HrException
{
    public static function invalidTransition(int $complaintId, string $from, string $to): self
    {
        return new self(sprintf('客诉 #%d 状态不允许从 %s 流转到 %s', $complaintId, $from, $to));
    }
}
