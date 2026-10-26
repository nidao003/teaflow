<?php

namespace App\Domain\Store\Enums;

/**
 * 门店营业状态（stores.status 字符串常量）。
 * 说明：既有 App\Models\Store 不改，营业状态由本域统一约束。
 */
final class StoreStatus
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const SUSPENDED = 'suspended';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::OPEN, self::CLOSED, self::SUSPENDED];
    }
}
