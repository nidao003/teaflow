<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exceptions;

/**
 * 预约容量不足（防超卖）。
 */
class CapacityExceededException extends FulfillmentException
{
    public static function forSlot(int $slotId, int $capacity, int $booked, int $requested): self
    {
        return new self(sprintf(
            '预约时段容量不足：slot=%d 容量 %d，已约 %d，本次 %d',
            $slotId,
            $capacity,
            $booked,
            $requested
        ));
    }
}
