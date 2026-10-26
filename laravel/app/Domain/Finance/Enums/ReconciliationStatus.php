<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

/**
 * 渠道对账状态：pending 待对账 / matched 已匹配 / exception 差异异常。
 */
enum ReconciliationStatus: string
{
    case PENDING = 'pending';
    case MATCHED = 'matched';
    case EXCEPTION = 'exception';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromValue(self|string|null $value, self $default = self::PENDING): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $resolved = self::tryFrom((string) $value);

        if ($resolved === null) {
            throw new \InvalidArgumentException('非法对账状态：'.(string) $value.'，允许值：'.implode(' / ', self::values()));
        }

        return $resolved;
    }
}
