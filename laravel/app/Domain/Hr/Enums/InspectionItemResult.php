<?php

declare(strict_types=1);

namespace App\Domain\Hr\Enums;

use InvalidArgumentException;

/**
 * 巡检检查项结果（inspection_items.result），null 表示未评分。
 */
final class InspectionItemResult
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    /** 不适用项，不计入评分分母 */
    public const NA = 'na';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PASSED, self::FAILED, self::NA];
    }

    public static function assertValid(string $result): void
    {
        if (! in_array($result, self::all(), true)) {
            throw new InvalidArgumentException('非法巡检项结果：'.$result);
        }
    }
}
