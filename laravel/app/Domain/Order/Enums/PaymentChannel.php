<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

use InvalidArgumentException;

/**
 * 支付渠道（payments.channel / payment_callbacks.channel）。
 */
enum PaymentChannel: string
{
    case WECHAT = 'wechat';
    case ALIPAY = 'alipay';
    case CASH = 'cash';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function isValid(string $channel): bool
    {
        return in_array($channel, self::values(), true);
    }

    public static function assertValid(string $channel): void
    {
        if (! self::isValid($channel)) {
            throw new InvalidArgumentException('非法支付渠道：'.$channel);
        }
    }
}
