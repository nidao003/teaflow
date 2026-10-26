<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 支付回调金额与支付单金额不一致。
 */
class PaymentAmountMismatchException extends OrderException
{
    public static function forOutTradeNo(string $outTradeNo, string $expected, string $actual): self
    {
        return new self(sprintf(
            '支付回调金额不一致：out_trade_no=%s，支付单金额=%s，回调金额=%s',
            $outTradeNo,
            $expected,
            $actual
        ));
    }
}
