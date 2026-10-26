<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\CostDaily;
use App\Domain\Finance\Models\ProfitDaily;
use App\Domain\Finance\Models\RevenueDaily;
use App\Domain\Finance\Support\Amount;
use App\Domain\Finance\Support\Period;
use Illuminate\Support\Facades\DB;

/**
 * 毛利服务：gross_profit = revenue - cost；毛利率按百分比（两位小数）。
 * revenue 取 revenue_daily.net_amount，cost 取 cost_daily 三项合计。
 */
class ProfitService
{
    /**
     * @return array{gross_profit:float, gross_margin:float}
     */
    public function compute(float $revenue, float $cost): array
    {
        $revenue = Amount::round2($revenue);
        $cost = Amount::round2($cost);
        $grossProfit = Amount::round2($revenue - $cost);

        return [
            'gross_profit' => $grossProfit,
            'gross_margin' => Amount::marginPercent($grossProfit, $revenue),
        ];
    }

    /**
     * 重算并落库（幂等）；收入/成本都没有记录时返回 null。
     */
    public function recalc(int $storeId, string|\DateTimeInterface $date): ?ProfitDaily
    {
        $day = Period::normalizeDate($date);

        $revenueRow = RevenueDaily::query()
            ->where('store_id', $storeId)
            ->where('date', $day)
            ->first();

        $costRow = CostDaily::query()
            ->where('store_id', $storeId)
            ->where('date', $day)
            ->first();

        if ($revenueRow === null && $costRow === null) {
            return null;
        }

        $revenue = $revenueRow === null ? 0.0 : (float) $revenueRow->net_amount;
        $cost = $costRow === null ? 0.0 : $costRow->total();
        $computed = $this->compute($revenue, $cost);

        return DB::transaction(fn (): ProfitDaily => ProfitDaily::query()->updateOrCreate(
            [
                'store_id' => $storeId,
                'date' => $day,
            ],
            [
                'revenue' => $revenue,
                'cost' => $cost,
                'gross_profit' => $computed['gross_profit'],
                'gross_margin' => $computed['gross_margin'],
            ]
        ));
    }
}
