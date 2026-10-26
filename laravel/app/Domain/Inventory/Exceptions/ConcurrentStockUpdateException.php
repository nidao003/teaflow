<?php

namespace App\Domain\Inventory\Exceptions;

/**
 * 乐观锁版本冲突（并发写入被抢先提交，由 StockService 有界重试）。
 */
class ConcurrentStockUpdateException extends InventoryException
{
    public static function forStockItem(int $stockItemId, int $expectedVersion): self
    {
        return new self(sprintf('库存并发写冲突：stock_item=%d 期望版本 %d 已被其他事务修改', $stockItemId, $expectedVersion));
    }
}
