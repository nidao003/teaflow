<?php

namespace App\Domain\Integration\Enums;

/**
 * 开放 API 客户端状态。
 */
final class ClientStatus
{
    public const ACTIVE = 'active';

    public const DISABLED = 'disabled';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ACTIVE, self::DISABLED];
    }
}
