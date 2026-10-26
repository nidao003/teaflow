<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 巡检单状态（inspections.status）。
 *
 * 流转：draft（填写中）→ passed / failed（提交评分）→ rectifying（整改中）→ closed（关闭）。
 */
final class InspectionStatus
{
    public const DRAFT = 'draft';

    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const RECTIFYING = 'rectifying';

    public const CLOSED = 'closed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::PASSED, self::FAILED, self::RECTIFYING, self::CLOSED];
    }

    public static function assertValid(string $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('非法巡检状态：'.$status);
        }
    }
}
