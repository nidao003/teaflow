<?php

declare(strict_types=1);

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Models\Target;
use App\Domain\Hr\Support\Decimal;
use InvalidArgumentException;

/**
 * 门店目标服务：目标设定、实际值回填、达成率进度。
 *
 * 达成率 = 实际 / 目标 * 100，保留两位小数（如 85.00 表示 85%）。
 */
class TargetService
{
    public function setTarget(
        int $storeId,
        string $period,
        string $metric,
        int|float|string $targetValue,
        int|float|string $actualValue = 0
    ): Target {
        if ($storeId <= 0) {
            throw new InvalidArgumentException('store_id 必须为正整数');
        }

        $period = $this->requireString($period, '目标周期', 32);
        $metric = $this->requireString($metric, '目标指标', 32);

        $targetCents = Decimal::toCents($targetValue);
        if ($targetCents <= 0) {
            throw new InvalidArgumentException('目标值必须大于 0');
        }

        $actualCents = Decimal::toCents($actualValue);
        if ($actualCents < 0) {
            throw new InvalidArgumentException('实际值不能为负数');
        }

        return Target::query()->updateOrCreate(
            ['store_id' => $storeId, 'period' => $period, 'metric' => $metric],
            [
                'target_value' => Decimal::fromCents($targetCents),
                'actual_value' => Decimal::fromCents($actualCents),
            ]
        );
    }

    public function recordActual(
        int $storeId,
        string $period,
        string $metric,
        int|float|string $actualValue
    ): Target {
        $target = Target::query()
            ->where('store_id', $storeId)
            ->where('period', $period)
            ->where('metric', $metric)
            ->firstOrFail();

        $actualCents = Decimal::toCents($actualValue);
        if ($actualCents < 0) {
            throw new InvalidArgumentException('实际值不能为负数');
        }

        $target->fill(['actual_value' => Decimal::fromCents($actualCents)])->save();

        return $target->refresh();
    }

    /**
     * @return array{
     *     target: Target,
     *     target_value: string,
     *     actual_value: string,
     *     attainment_rate: string,
     *     gap: string,
     *     achieved: bool
     * }
     */
    public function progress(int $storeId, string $period, string $metric): array
    {
        $target = Target::query()
            ->where('store_id', $storeId)
            ->where('period', $period)
            ->where('metric', $metric)
            ->firstOrFail();

        $targetCents = Decimal::toCents((string) $target->target_value);
        $actualCents = Decimal::toCents((string) $target->actual_value);

        return [
            'target' => $target,
            'target_value' => Decimal::fromCents($targetCents),
            'actual_value' => Decimal::fromCents($actualCents),
            'attainment_rate' => Decimal::percent($actualCents, $targetCents),
            'gap' => Decimal::fromCents(max(0, $targetCents - $actualCents)),
            'achieved' => $actualCents >= $targetCents,
        ];
    }

    protected function requireString(mixed $value, string $label, int $maxLength): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException($label.'不能为空');
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException($label.'不能为空');
        }
        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException($label.'长度不能超过 '.$maxLength.' 个字符');
        }

        return $trimmed;
    }
}
