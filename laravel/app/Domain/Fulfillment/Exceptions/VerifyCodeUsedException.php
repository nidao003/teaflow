<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exceptions;

/**
 * 核销码已被使用（一次性）。
 */
class VerifyCodeUsedException extends FulfillmentException
{
    public static function forCode(string $code, ?string $usedAt = null): self
    {
        return new self($usedAt === null
            ? '核销码已被使用：'.$code
            : sprintf('核销码已被使用：%s（使用时间 %s）', $code, $usedAt));
    }
}
