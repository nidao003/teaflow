<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exceptions;

/**
 * 核销码已过期。
 */
class VerifyCodeExpiredException extends FulfillmentException
{
    public static function forCode(string $code, ?string $expiresAt = null): self
    {
        return new self($expiresAt === null
            ? '核销码已过期：'.$code
            : sprintf('核销码已过期：%s（过期时间 %s）', $code, $expiresAt));
    }
}
