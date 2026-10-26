<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 非法的订单状态迁移。
 */
class IllegalOrderTransitionException extends OrderException
{
    public static function notAllowed(?string $orderNumber, string $fromStatus, string $toStatus): self
    {
        $prefix = $orderNumber === null ? '' : '订单 '.$orderNumber.' ';

        return new self(sprintf('%s状态迁移非法：%s → %s', $prefix, $fromStatus, $toStatus));
    }
}
