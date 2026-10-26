<?php

namespace App\Domain\Store\Exceptions;

/**
 * 区域树操作异常（移动成环、跨租户、区域停用等）。
 */
class RegionException extends StoreException
{
    public static function selfAsParent(): self
    {
        return new self('不能把区域挂到自身之下');
    }

    public static function cycle(int $regionId, int $parentId): self
    {
        return new self("不能把区域 {$regionId} 移动到它的后代 {$parentId} 之下");
    }

    public static function tenantMismatch(): self
    {
        return new self('不能跨租户移动区域');
    }

    public static function disabled(int $regionId): self
    {
        return new self("区域 {$regionId} 已停用，不能绑定门店");
    }

    public static function storeNotFound(int $storeId): self
    {
        return new self("门店 {$storeId} 不存在或已删除");
    }
}
