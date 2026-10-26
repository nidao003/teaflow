<?php

namespace App\Domain\Inventory\Enums;

/**
 * 仓库类型：门店仓 / 中央仓 / 虚拟仓。
 */
enum WarehouseType: string
{
    case STORE = 'store';
    case CENTRAL = 'central';
    case VIRTUAL = 'virtual';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromValue(self|string|null $value, self $default = self::STORE): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $resolved = self::tryFrom((string) $value);

        if ($resolved === null) {
            throw new \InvalidArgumentException('非法仓库类型：'.(string) $value.'，允许值：'.implode(' / ', self::values()));
        }

        return $resolved;
    }
}
