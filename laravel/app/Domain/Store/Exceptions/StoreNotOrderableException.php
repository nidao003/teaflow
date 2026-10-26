<?php

namespace App\Domain\Store\Exceptions;

/**
 * 门店不可下单（闭店、停业、非营业时间、已删除）。
 */
class StoreNotOrderableException extends StoreException
{
    public static function forState(int $storeId, string $state): self
    {
        return new self("门店 {$storeId} 当前不可下单，状态：{$state}");
    }
}
