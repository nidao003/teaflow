<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Exceptions;

/**
 * 标签/会员价等营销子域异常。
 */
class MarketingRuleException extends MarketingException
{
    public static function duplicateTag(string $name): self
    {
        return new self('会员标签已存在：'.$name);
    }
}
