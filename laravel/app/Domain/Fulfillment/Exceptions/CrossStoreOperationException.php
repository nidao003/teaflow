<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exceptions;

/**
 * 跨店操作被拒绝（核销码/预约必须属于当前门店）。
 */
class CrossStoreOperationException extends FulfillmentException
{
    public static function for(string $resource, int $expectedStoreId, int $actualStoreId): self
    {
        return new self(sprintf(
            '%s 不属于当前门店：要求 store_id=%d，实际 store_id=%d',
            $resource,
            $expectedStoreId,
            $actualStoreId
        ));
    }
}
