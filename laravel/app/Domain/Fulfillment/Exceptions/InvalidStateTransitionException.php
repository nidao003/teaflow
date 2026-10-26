<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exceptions;

/**
 * 单据状态机非法流转。
 */
class InvalidStateTransitionException extends FulfillmentException
{
    public static function for(string $document, string $from, string $to): self
    {
        return new self(sprintf('%s 不允许状态流转：%s → %s', $document, $from, $to));
    }
}
