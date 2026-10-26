<?php

namespace App\Domain\Inventory\Exceptions;

/**
 * 单据状态机非法流转。
 */
class InvalidStateTransitionException extends InventoryException
{
    public static function for(string $document, string $from, string $to): self
    {
        return new self(sprintf('%s 不允许状态流转：%s → %s', $document, $from, $to));
    }
}
