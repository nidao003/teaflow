<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 支付单不存在。
 */
class PaymentNotFoundException extends OrderException
{
    public static function forOutTradeNo(string $outTradeNo): self
    {
        return new self('支付单不存在：'.$outTradeNo);
    }
}
