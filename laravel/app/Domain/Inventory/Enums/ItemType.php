<?php

namespace App\Domain\Inventory\Enums;

/**
 * 物料类型：原料 / 商品（成品）。
 */
enum ItemType: string
{
    case MATERIAL = 'material';
    case PRODUCT = 'product';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromValue(self|string|null $value, self $default = self::MATERIAL): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $resolved = self::tryFrom((string) $value);

        if ($resolved === null) {
            throw new \InvalidArgumentException('非法 item_type：'.(string) $value.'，允许值：'.implode(' / ', self::values()));
        }

        return $resolved;
    }
}
