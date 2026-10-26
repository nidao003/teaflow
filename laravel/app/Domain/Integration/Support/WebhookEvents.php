<?php

namespace App\Domain\Integration\Support;

/**
 * 可订阅的 Webhook 事件注册表。
 *
 * 约定：<domain>.<action>，与权限点命名风格一致；新增事件必须在此登记，
 * WebhookEndpointService 会拒绝未登记的事件，避免拼写错误造成「订阅了但永远不发」。
 */
final class WebhookEvents
{
    public const ORDER_CREATED = 'order.created';

    public const ORDER_PAID = 'order.paid';

    public const ORDER_REFUNDED = 'order.refunded';

    public const MEMBER_REGISTERED = 'member.registered';

    public const STOCK_LOW = 'stock.low';

    public const STORE_UPDATED = 'store.updated';

    public const COUPON_ISSUED = 'coupon.issued';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ORDER_CREATED,
            self::ORDER_PAID,
            self::ORDER_REFUNDED,
            self::MEMBER_REGISTERED,
            self::STOCK_LOW,
            self::STORE_UPDATED,
            self::COUPON_ISSUED,
        ];
    }

    public static function isKnown(string $event): bool
    {
        return in_array($event, self::all(), true);
    }
}
