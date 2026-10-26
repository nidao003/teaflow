<?php

namespace App\Domain\Inventory\Exceptions;

/**
 * 可用库存不足（负库存拒绝）。
 */
class InsufficientStockException extends InventoryException
{
    public function __construct(
        public readonly int $warehouseId,
        public readonly string $itemType,
        public readonly int $itemId,
        public readonly float $requested,
        public readonly float $available,
        string $message = ''
    ) {
        if ($message === '') {
            $message = sprintf(
                '库存不足：warehouse=%d item_type=%s item_id=%d 需要 %s，可用 %s',
                $warehouseId,
                $itemType,
                $itemId,
                $requested,
                $available
            );
        }

        parent::__construct($message);
    }
}
