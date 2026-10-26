<?php

namespace App\Domain\Integration\Enums;

/**
 * Webhook 投递状态。
 */
final class DeliveryStatus
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PENDING, self::SUCCESS, self::FAILED];
    }
}
