<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 订单已支付，不能再发起新的支付。
 */
class OrderAlreadyPaidException extends OrderException
{
    public static function forOrderNumber(string $orderNumber): self
    {
        return new self('订单已支付，不能重复发起支付：'.$orderNumber);
    }
}
