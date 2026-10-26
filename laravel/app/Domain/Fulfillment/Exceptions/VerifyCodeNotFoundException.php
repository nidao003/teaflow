<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exceptions;

/**
 * 核销码不存在。
 */
class VerifyCodeNotFoundException extends FulfillmentException
{
    public static function forCode(string $code): self
    {
        return new self('核销码不存在：'.$code);
    }
}
