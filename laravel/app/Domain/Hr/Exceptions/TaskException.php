<?php

declare(strict_types=1);

namespace App\Domain\Hr\Exceptions;

/**
 * 任务异常：状态流转非法等。
 */
class TaskException extends HrException
{
    public static function invalidTransition(int $taskId, string $from, string $to): self
    {
        return new self(sprintf('任务 #%d 状态不允许从 %s 流转到 %s', $taskId, $from, $to));
    }
}
