<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use App\Domain\Order\Enums\RefundStatus;

/**
 * 退款单状态不允许当前操作。
 */
class InvalidRefundStatusException extends OrderException
{
    public static function expected(string $refundNo, RefundStatus|string $expected, RefundStatus|string $actual): self
    {
        $expectedValue = $expected instanceof RefundStatus ? $expected->value : $expected;
        $actualValue = $actual instanceof RefundStatus ? $actual->value : $actual;

        return new self(sprintf(
            '退款单状态非法：refund_no=%s，期望 %s，实际 %s',
            $refundNo,
            $expectedValue,
            $actualValue
        ));
    }
}
