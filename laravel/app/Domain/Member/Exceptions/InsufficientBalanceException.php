<?php

declare(strict_types=1);

namespace App\Domain\Member\Exceptions;

/**
 * 余额/积分不足，拒绝出账。
 */
class InsufficientBalanceException extends MemberException
{
    public static function forAccount(string $accountType, string $required, string $available): self
    {
        return new self(sprintf(
            '会员账户[%s]余额不足：本次需要 %s，当前可用 %s',
            $accountType,
            $required,
            $available
        ));
    }
}
