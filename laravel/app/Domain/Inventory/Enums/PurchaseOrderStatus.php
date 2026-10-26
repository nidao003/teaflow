<?php

namespace App\Domain\Inventory\Enums;

/**
 * 采购单状态机：draft → approved → partial_received / received → closed / cancelled。
 */
enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case PARTIAL_RECEIVED = 'partial_received';
    case RECEIVED = 'received';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * 允许的下一状态。
     *
     * @return self[]
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::DRAFT => [self::APPROVED, self::CANCELLED],
            self::APPROVED => [self::PARTIAL_RECEIVED, self::RECEIVED, self::CANCELLED],
            self::PARTIAL_RECEIVED => [self::PARTIAL_RECEIVED, self::RECEIVED],
            self::RECEIVED => [self::CLOSED],
            self::CLOSED, self::CANCELLED => [],
        };
    }

    public function canTransitTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::DRAFT, self::APPROVED, self::PARTIAL_RECEIVED], true);
    }
}
