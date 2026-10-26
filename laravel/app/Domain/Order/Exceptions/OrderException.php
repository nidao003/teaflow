<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use RuntimeException;

/**
 * 订单/支付/退款域业务异常基类。
 */
class OrderException extends RuntimeException
{
    public static function invalidOrderAmount(string $orderNumber): self
    {
        return new self('订单金额非法（必须大于 0）：'.$orderNumber);
    }

    public static function idempotencyKeyConflict(string $idempotencyKey, string $orderNumber): self
    {
        return new self(sprintf('幂等键 %s 已绑定到其它订单，不能用于订单 %s', $idempotencyKey, $orderNumber));
    }

    public static function channelMismatch(string $outTradeNo, string $paymentChannel, string $callbackChannel): self
    {
        return new self(sprintf(
            '回调渠道与支付单不一致：out_trade_no=%s，支付单渠道=%s，回调渠道=%s',
            $outTradeNo,
            $paymentChannel,
            $callbackChannel
        ));
    }
}
