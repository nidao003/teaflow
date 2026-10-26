<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 支付回调验签失败。
 */
class UnverifiedCallbackException extends OrderException
{
    public static function forNotifyId(string $notifyId, string $channel): self
    {
        return new self(sprintf('支付回调验签失败：channel=%s，notify_id=%s', $channel, $notifyId));
    }
}
