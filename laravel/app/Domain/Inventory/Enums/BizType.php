<?php

namespace App\Domain\Inventory\Enums;

/**
 * 库存流水业务类型（库存变动的唯一出口按 biz_type 归类）。
 */
enum BizType: string
{
    case PURCHASE = 'purchase';
    case RECEIPT = 'receipt';
    case CONSUME = 'consume';
    case TRANSFER = 'transfer';
    case ADJUST = 'adjust';
    case WASTE = 'waste';
    case RETURN = 'return';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromValue(self|string|null $value, self $default = self::RECEIPT): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $resolved = self::tryFrom((string) $value);

        if ($resolved === null) {
            throw new \InvalidArgumentException('非法 biz_type：'.(string) $value.'，允许值：'.implode(' / ', self::values()));
        }

        return $resolved;
    }
}
