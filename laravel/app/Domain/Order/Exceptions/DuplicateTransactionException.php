<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 渠道交易号已被其它支付单占用。
 */
class DuplicateTransactionException extends OrderException
{
    public static function forTransactionId(string $transactionId): self
    {
        return new self('渠道交易号已存在，疑似重复回调：'.$transactionId);
    }
}
