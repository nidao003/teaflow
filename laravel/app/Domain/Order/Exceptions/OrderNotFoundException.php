<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 订单不存在。
 */
class OrderNotFoundException extends OrderException
{
    public static function forOrderNumber(string $orderNumber): self
    {
        return new self('订单不存在：'.$orderNumber);
    }
}
