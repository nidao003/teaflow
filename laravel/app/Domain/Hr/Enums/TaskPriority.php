<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 任务优先级（tasks.priority）。
 */
final class TaskPriority
{
    public const LOW = 'low';

    public const NORMAL = 'normal';

    public const HIGH = 'high';

    public const URGENT = 'urgent';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::LOW, self::NORMAL, self::HIGH, self::URGENT];
    }

    public static function assertValid(string $priority): void
    {
        if (! in_array($priority, self::all(), true)) {
            throw new InvalidArgumentException('非法任务优先级：'.$priority);
        }
    }
}
