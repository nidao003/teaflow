<?php

namespace App\Domain\Org\Exceptions;

use RuntimeException;

/**
 * 组织树操作异常（如：移动后形成环、父级不存在、跨租户移动等）。
 */
class OrgTreeException extends RuntimeException
{
    public static function selfAsParent(): self
    {
        return new self('不能把组织挂到自身之下');
    }

    public static function cycle(int $unitId, int $parentId): self
    {
        return new self("不能把组织 {$unitId} 移动到它的后代 {$parentId} 之下");
    }

    public static function tenantMismatch(): self
    {
        return new self('不能跨租户移动组织');
    }

    public static function invalidType(string $type): self
    {
        return new self("非法的组织类型：{$type}");
    }
}
