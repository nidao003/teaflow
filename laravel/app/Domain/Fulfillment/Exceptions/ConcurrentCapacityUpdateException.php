<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exceptions;

/**
 * 预约容量并发写冲突（booked_count 条件更新失败，由 ReservationService 有界重试）。
 */
class ConcurrentCapacityUpdateException extends FulfillmentException
{
    public static function forSlot(int $slotId): self
    {
        return new self('预约时段容量并发写冲突：slot='.$slotId);
    }
}
