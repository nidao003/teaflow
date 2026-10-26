<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Enums;

/**
 * 预约时段状态：active 开放 / closed 关闭。
 */
enum ReservationSlotStatus: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromValue(self|string|null $value, self $default = self::ACTIVE): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $resolved = self::tryFrom((string) $value);

        if ($resolved === null) {
            throw new \InvalidArgumentException('非法时段状态：'.(string) $value.'，允许值：'.implode(' / ', self::values()));
        }

        return $resolved;
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::ACTIVE => [self::CLOSED],
            self::CLOSED => [],
        };
    }

    public function canTransitTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
