<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\CostDaily;
use App\Domain\Finance\Models\ProfitDaily;
use App\Domain\Finance\Models\RevenueDaily;
use App\Domain\Finance\Support\Period;

/**
 * 日结服务：生成/重算某门店某营业日的收入、成本、毛利三张日汇总。
 *
 * - 幂等：重复日结只 updateOrCreate，不重复插入；
 * - 无数据日不硬造：收入与成本都没有数据时返回 null 且不落任何行；
 * - options 同时透传给 RevenueService（payment_fee/fee_rate）与 CostService（material/labor/other 等）。
 */
class DailyCloseService
{
    public function __construct(
        private readonly RevenueService $revenueService,
        private readonly CostService $costService,
        private readonly ProfitService $profitService
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     store_id:int,
     *     date:string,
     *     revenue:RevenueDaily|null,
     *     cost:CostDaily|null,
     *     profit:ProfitDaily|null
     * }|null
     */
    public function close(int $storeId, string|\DateTimeInterface $date, array $options = []): ?array
    {
        $day = Period::normalizeDate($date);

        $revenue = $this->revenueService->recalc($storeId, $day, $options);
        $cost = $this->costService->recalc($storeId, $day, $options);

        if ($revenue === null && $cost === null) {
            return null;
        }

        $profit = $this->profitService->recalc($storeId, $day);

        return [
            'store_id' => $storeId,
            'date' => $day,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
        ];
    }

    /**
     * 批量日结（含首尾），只返回有数据的日期。
     *
     * @param  array<string, mixed>  $options
     * @return list<array{
     *     store_id:int,
     *     date:string,
     *     revenue:RevenueDaily|null,
     *     cost:CostDaily|null,
     *     profit:ProfitDaily|null
     * }>
     */
    public function closeRange(
        int $storeId,
        string|\DateTimeInterface $from,
        string|\DateTimeInterface $to,
        array $options = []
    ): array {
        $results = [];

        foreach (Period::eachDate($from, $to) as $day) {
            $closed = $this->close($storeId, $day, $options);

            if ($closed !== null) {
                $results[] = $closed;
            }
        }

        return $results;
    }
}
