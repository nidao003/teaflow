<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\CostDaily;
use App\Domain\Finance\Support\Amount;
use App\Domain\Finance\Support\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 成本服务：原料 / 人工 / 其他成本日汇总。
 *
 * 原料成本取值优先级（不硬依赖其他 Domain 类，只按表结构尽力读取）：
 * 1. options.material_cost 显式传入；
 * 2. options.sold_sku_qty（SKU id => 销量）结合 Product 域 cost_cards 最新版本单位成本折算；
 * 3. Inventory 域 stock_ledger 中当店当日 biz_type=consume 的流水金额合计；
 * 4. options.fallback_material_cost（缺省 0）。
 *
 * 人工/其他成本只接受显式传入，缺省 0。
 */
class CostService
{
    /**
     * 聚合某门店某营业日的成本（只读，不落库）。
     *
     * @param  array{
     *     material_cost?:float|int|string,
     *     labor_cost?:float|int|string,
     *     other_cost?:float|int|string,
     *     fallback_material_cost?:float|int|string,
     *     sold_sku_qty?:array<int|string, float|int|string>
     * }  $options
     * @return array{
     *     store_id:int, date:string, material_cost:float, labor_cost:float,
     *     other_cost:float, total_cost:float, has_data:bool
     * }
     */
    public function aggregate(int $storeId, string|\DateTimeInterface $date, array $options = []): array
    {
        $day = Period::normalizeDate($date);

        $materialCost = $this->materialCost($storeId, $day, $options);
        $laborCost = Amount::round2($options['labor_cost'] ?? 0);
        $otherCost = Amount::round2($options['other_cost'] ?? 0);
        $totalCost = Amount::round2($materialCost + $laborCost + $otherCost);

        $hasData = $totalCost !== 0.0
            || array_key_exists('material_cost', $options)
            || array_key_exists('labor_cost', $options)
            || array_key_exists('other_cost', $options)
            || (isset($options['sold_sku_qty']) && is_array($options['sold_sku_qty']) && $options['sold_sku_qty'] !== []);

        return [
            'store_id' => $storeId,
            'date' => $day,
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'other_cost' => $otherCost,
            'total_cost' => $totalCost,
            'has_data' => $hasData,
        ];
    }

    /**
     * 重算并落库（幂等：同门店同日 updateOrCreate）；无成本数据返回 null。
     *
     * @param  array<string, mixed>  $options
     */
    public function recalc(int $storeId, string|\DateTimeInterface $date, array $options = []): ?CostDaily
    {
        $aggregate = $this->aggregate($storeId, $date, $options);

        if (! $aggregate['has_data']) {
            return null;
        }

        return DB::transaction(fn (): CostDaily => CostDaily::query()->updateOrCreate(
            [
                'store_id' => $aggregate['store_id'],
                'date' => $aggregate['date'],
            ],
            [
                'material_cost' => $aggregate['material_cost'],
                'labor_cost' => $aggregate['labor_cost'],
                'other_cost' => $aggregate['other_cost'],
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function materialCost(int $storeId, string $date, array $options): float
    {
        if (array_key_exists('material_cost', $options)) {
            return Amount::round2($options['material_cost']);
        }

        $soldSkuQty = $options['sold_sku_qty'] ?? null;
        if (is_array($soldSkuQty) && $soldSkuQty !== []) {
            $fromCostCards = $this->materialCostFromCostCards($soldSkuQty);
            if ($fromCostCards !== null) {
                return $fromCostCards;
            }
        }

        $fromLedger = $this->materialCostFromStockLedger($storeId, $date);
        if ($fromLedger !== null) {
            return $fromLedger;
        }

        return Amount::round2($options['fallback_material_cost'] ?? 0);
    }

    /**
     * 从 stock_ledger（Inventory 域）读取当店当日 consume 流水金额合计。
     *
     * @return float|null 表不存在或无流水时返回 null（交由 fallback）
     */
    private function materialCostFromStockLedger(int $storeId, string $date): ?float
    {
        if (! Schema::hasTable('stock_ledger') || ! Schema::hasTable('warehouses')) {
            return null;
        }

        [$start, $end] = Period::bounds($date);

        $query = DB::table('stock_ledger as sl')
            ->join('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->where('w.store_id', $storeId)
            ->where('sl.biz_type', 'consume')
            ->whereBetween('sl.created_at', [$start, $end]);

        if (! (clone $query)->exists()) {
            return null;
        }

        return Amount::round2($query->sum('sl.amount'));
    }

    /**
     * SKU 销量 × cost_cards 最新版单位成本。
     *
     * @param  array<int|string, mixed>  $soldSkuQty
     * @return float|null 表不存在时返回 null
     */
    private function materialCostFromCostCards(array $soldSkuQty): ?float
    {
        if (! Schema::hasTable('cost_cards')) {
            return null;
        }

        $total = 0.0;

        foreach ($soldSkuQty as $skuId => $qty) {
            $unitCost = DB::table('cost_cards')
                ->where('product_sku_id', (int) $skuId)
                ->orderByDesc('version')
                ->value('unit_cost');

            if ($unitCost === null) {
                continue;
            }

            $total += (float) $qty * (float) $unitCost;
        }

        return Amount::round2($total);
    }
}
