<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 退款单号已被其它订单占用。
 */
class DuplicateRefundNoException extends OrderException
{
    public static function forRefundNo(string $refundNo, string $orderNumber): self
    {
        return new self(sprintf('退款单号 %s 已被其它订单占用（当前订单 %s）', $refundNo, $orderNumber));
    }
}
