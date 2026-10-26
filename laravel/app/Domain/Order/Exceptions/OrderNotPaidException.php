<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 订单未支付，不能退款。
 */
class OrderNotPaidException extends OrderException
{
    public static function forOrderNumber(string $orderNumber): self
    {
        return new self('订单未支付，不能退款：'.$orderNumber);
    }
}
