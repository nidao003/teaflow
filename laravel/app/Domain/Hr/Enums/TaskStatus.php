<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 任务状态（tasks.status）。
 *
 * 流转：pending → in_progress → done；pending/in_progress → cancelled。
 */
final class TaskStatus
{
    public const PENDING = 'pending';

    public const IN_PROGRESS = 'in_progress';

    public const DONE = 'done';

    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::IN_PROGRESS, self::DONE, self::CANCELLED];
    }

    public static function assertValid(string $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('非法任务状态：'.$status);
        }
    }

    /**
     * 未结束状态（可用于逾期判断）。
     *
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return [self::PENDING, self::IN_PROGRESS];
    }
}
