<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\Exceptions\MaterialCostUnavailableException;
use App\Domain\Product\Models\CostCard;
use App\Domain\Product\Models\Material;
use App\Domain\Product\Models\ProductSku;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * 成本服务：按 BOM 逐行计算 unit_cost = Σ(qty × (1 + loss_rate) × material_avg_cost)，并落不可变成本卡快照。
 *
 * 原料单价来源优先级：
 * 1. 库存域 stock_items.avg_cost（按数量加权平均；不引用 Inventory 类，只读表，表不存在则跳过）；
 * 2. 调用方传入的 fallback：$options['material_costs'][material_id]；
 * 3. 同 SKU 历史成本卡快照中的同原料单价；
 * 4. 都没有时抛 MaterialCostUnavailableException。
 */
class CostService
{
    public const SOURCE_STOCK_ITEMS = 'stock_items';

    public const SOURCE_FALLBACK = 'fallback';

    public const SOURCE_COST_CARD = 'cost_card';

    private RecipeService $recipeService;

    public function __construct(?RecipeService $recipeService = null)
    {
        $this->recipeService = $recipeService ?? new RecipeService;
    }

    /**
     * @param  array{material_costs?: array<int|string, int|float|string>}  $options
     */
    public function calc(int $skuId, DateTimeInterface|string|null $date = null, array $options = []): CostCard
    {
        $day = $date === null ? Carbon::today() : Carbon::parse($date)->startOfDay();
        $fallbackCosts = $this->normalizeFallbackCosts($options['material_costs'] ?? []);

        // 无生效配方时抛 NoActiveRecipeException，不产生成本卡
        $recipe = $this->recipeService->activeForSku($skuId, $day);

        $items = [];
        $total = 0.0;

        foreach ($recipe->items as $item) {
            $materialId = (int) $item->material_id;
            $resolved = $this->resolveMaterialCost($materialId, $fallbackCosts, $skuId);

            $qty = (float) $item->qty;
            $lossRate = (float) $item->loss_rate;
            $effectiveQty = round($qty * (1 + $lossRate), 6);
            $lineCost = round($effectiveQty * $resolved['cost'], 2);

            $total += $effectiveQty * $resolved['cost'];

            $material = $item->material;
            $items[] = [
                'material_id' => $materialId,
                'material_name' => $material?->name,
                'unit' => $item->unit ?: $material?->unit,
                'qty' => $qty,
                'loss_rate' => $lossRate,
                'effective_qty' => $effectiveQty,
                'unit_cost' => round($resolved['cost'], 4),
                'line_cost' => $lineCost,
                'cost_source' => $resolved['source'],
            ];
        }

        $total = round($total, 2);

        $snapshot = [
            'product_sku_id' => $skuId,
            'recipe_id' => $recipe->id,
            'recipe_version' => $recipe->version,
            'recipe_effective_from' => $recipe->effective_from->toDateString(),
            'recipe_effective_to' => $recipe->effective_to?->toDateString(),
            'calc_date' => $day->toDateString(),
            'calculated_at' => Carbon::now()->toIso8601String(),
            'items' => $items,
            'unit_cost' => $total,
        ];

        return DB::transaction(function () use ($skuId, $recipe, $day, $total, $snapshot) {
            ProductSku::query()->whereKey($skuId)->lockForUpdate()->first();

            $version = ((int) CostCard::query()->where('product_sku_id', $skuId)->max('version')) + 1;

            $card = CostCard::create([
                'product_sku_id' => $skuId,
                'version' => $version,
                'recipe_id' => $recipe->id,
                'unit_cost' => $total,
                'calc_at' => $day->toDateTimeString(),
                'snapshot_json' => $snapshot,
            ]);

            // 只在本次业务时点不早于已有成本卡时回写 SKU 最近成本，避免历史重算覆盖当前值
            $latestCalcAt = CostCard::query()
                ->where('product_sku_id', $skuId)
                ->where('id', '!=', $card->id)
                ->max('calc_at');

            if ($latestCalcAt === null || $day->gte(Carbon::parse($latestCalcAt))) {
                ProductSku::query()->whereKey($skuId)->update(['cost' => $total]);
            }

            return $card->refresh();
        });
    }

    public function latest(int $skuId): ?CostCard
    {
        return CostCard::query()
            ->where('product_sku_id', $skuId)
            ->orderByDesc('version')
            ->first();
    }

    public function history(int $skuId, int $limit = 20): Collection
    {
        return CostCard::query()
            ->where('product_sku_id', $skuId)
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{cost: float, source: string}
     */
    private function resolveMaterialCost(int $materialId, array $fallbackCosts, int $skuId): array
    {
        // 1) 库存域库存余额的加权平均成本（不依赖 Inventory 类；库存域未建表时跳过）
        if (Schema::hasTable('stock_items')) {
            $rows = DB::table('stock_items')
                ->where('item_type', 'material')
                ->where('item_id', $materialId)
                ->whereNotNull('avg_cost')
                ->get(['qty', 'avg_cost']);

            $totalQty = 0.0;
            $amount = 0.0;

            foreach ($rows as $row) {
                $qty = (float) $row->qty;
                if ($qty <= 0) {
                    continue;
                }
                $totalQty += $qty;
                $amount += $qty * (float) $row->avg_cost;
            }

            if ($totalQty > 0 && $amount > 0) {
                return [
                    'cost' => $amount / $totalQty,
                    'source' => self::SOURCE_STOCK_ITEMS,
                ];
            }
        }

        // 2) 调用方显式传入的兜底单价
        if (array_key_exists($materialId, $fallbackCosts)) {
            return [
                'cost' => $fallbackCosts[$materialId],
                'source' => self::SOURCE_FALLBACK,
            ];
        }

        // 3) 同 SKU 历史成本卡快照中的同原料单价
        $cards = CostCard::query()
            ->where('product_sku_id', $skuId)
            ->orderByDesc('version')
            ->get(['snapshot_json']);

        foreach ($cards as $card) {
            foreach (($card->snapshot_json['items'] ?? []) as $snapshotItem) {
                if ((int) ($snapshotItem['material_id'] ?? 0) === $materialId && isset($snapshotItem['unit_cost'])) {
                    return [
                        'cost' => (float) $snapshotItem['unit_cost'],
                        'source' => self::SOURCE_COST_CARD,
                    ];
                }
            }
        }

        $material = Material::query()->find($materialId);
        $label = $material?->name ?? "ID {$materialId}";

        throw new MaterialCostUnavailableException(
            "原料「{$label}」没有可用单价：stock_items 无该原料库存成本，未传入 fallback，历史成本卡中也无记录"
        );
    }

    /**
     * @param  array<int|string, int|float|string>  $costs
     * @return array<int, float>
     */
    private function normalizeFallbackCosts(array $costs): array
    {
        $normalized = [];

        foreach ($costs as $materialId => $cost) {
            if (! is_numeric($cost) || (float) $cost < 0) {
                throw new InvalidArgumentException("material_costs[{$materialId}] 必须为非负数字");
            }
            $normalized[(int) $materialId] = (float) $cost;
        }

        return $normalized;
    }
}
