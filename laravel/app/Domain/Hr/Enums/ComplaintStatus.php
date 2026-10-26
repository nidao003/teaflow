<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 客诉状态（complaints.status）。
 *
 * 流转：pending（待受理）→ processing（处理中）→ resolved（已解决）→ closed（已关闭）。
 */
final class ComplaintStatus
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::PROCESSING, self::RESOLVED, self::CLOSED];
    }

    public static function assertValid(string $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('非法客诉状态：'.$status);
        }
    }
}
