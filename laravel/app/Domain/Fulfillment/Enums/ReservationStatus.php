<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Enums;

/**
 * 预约单状态机：booked → arrived → converted；booked → cancelled / no_show。
 */
enum ReservationStatus: string
{
    case BOOKED = 'booked';
    case ARRIVED = 'arrived';
    case CONVERTED = 'converted';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::BOOKED => [self::ARRIVED, self::CANCELLED, self::NO_SHOW],
            self::ARRIVED => [self::CONVERTED],
            self::CONVERTED, self::CANCELLED, self::NO_SHOW => [],
        };
    }

    public function canTransitTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
