<?php

declare(strict_types=1);

namespace App\Domain\Member\Exceptions;

/**
 * 会员台账只追加：任何更新/删除操作都会被拒绝。
 */
class LedgerImmutableException extends MemberException
{
    public const MESSAGE = '会员台账只允许追加，不允许修改或删除';

    public static function cannotUpdate(int|string|null $id = null): self
    {
        return new self(self::MESSAGE.'（拒绝更新 id='.($id ?? 'unknown').'）');
    }

    public static function cannotDelete(int|string|null $id = null): self
    {
        return new self(self::MESSAGE.'（拒绝删除 id='.($id ?? 'unknown').'）');
    }
}
