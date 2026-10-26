<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use App\Domain\Order\Enums\PaymentStatus;

/**
 * 支付单当前状态不允许再被支付（已关闭/已失败）。
 */
class PaymentNotPayableException extends OrderException
{
    public static function forOutTradeNo(string $outTradeNo, PaymentStatus|string $status): self
    {
        $statusValue = $status instanceof PaymentStatus ? $status->value : $status;

        return new self(sprintf('支付单当前状态不可支付：out_trade_no=%s，status=%s', $outTradeNo, $statusValue));
    }
}
