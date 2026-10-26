<?php

namespace App\Domain\Integration\Enums;

/**
 * Webhook 端点状态。
 */
final class EndpointStatus
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
