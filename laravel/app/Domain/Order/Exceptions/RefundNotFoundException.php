<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 退款单不存在。
 */
class RefundNotFoundException extends OrderException
{
    public static function forRefundNo(string $refundNo): self
    {
        return new self('退款单不存在：'.$refundNo);
    }
}
