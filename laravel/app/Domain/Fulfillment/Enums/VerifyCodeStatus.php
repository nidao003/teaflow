<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Enums;

/**
 * 核销码状态机：issued → used / expired / void（终态不可再流转）。
 */
enum VerifyCodeStatus: string
{
    case ISSUED = 'issued';
    case USED = 'used';
    case EXPIRED = 'expired';
    case VOID = 'void';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::ISSUED => [self::USED, self::EXPIRED, self::VOID],
            self::USED, self::EXPIRED, self::VOID => [],
        };
    }

    public function canTransitTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
