<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Enums;

/**
 * 核销码类型：取餐码 / 券码。
 */
enum VerifyCodeType: string
{
    case PICKUP = 'pickup';
    case COUPON = 'coupon';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromValue(self|string|null $value, self $default = self::PICKUP): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $resolved = self::tryFrom((string) $value);

        if ($resolved === null) {
            throw new \InvalidArgumentException('非法核销码类型：'.(string) $value.'，允许值：'.implode(' / ', self::values()));
        }

        return $resolved;
    }
}
