<?php

namespace App\Domain\Integration\Exceptions;

use RuntimeException;

/**
 * 集成/开放域基础异常。
 */
class IntegrationException extends RuntimeException
{
    public static function unknownEvent(string $event): self
    {
        return new self("未登记的 Webhook 事件：{$event}");
    }

    public static function invalidUrl(string $url): self
    {
        return new self("非法的回调地址：{$url}");
    }

    public static function missingEndpoint(int $deliveryId): self
    {
        return new self("投递记录 {$deliveryId} 找不到所属端点");
    }
}
