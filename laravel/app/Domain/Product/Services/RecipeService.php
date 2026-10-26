<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\Exceptions\NoActiveRecipeException;
use App\Domain\Product\Exceptions\RecipeVersionOverlapException;
use App\Domain\Product\Models\Material;
use App\Domain\Product\Models\ProductSku;
use App\Domain\Product\Models\Recipe;
use App\Domain\Product\Models\RecipeItem;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 配方（BOM）版本服务：版本按有效日期区间生效，区间不可重叠。
 */
class RecipeService
{
    /**
     * 创建配方版本（默认 draft）。
     *
     * @param  array<int, array{material_id:int|string, qty:int|float|string, unit?:string, loss_rate?:int|float|string}>  $items
     */
    public function createVersion(
        int $skuId,
        array $items,
        DateTimeInterface|string $effectiveFrom,
        DateTimeInterface|string|null $effectiveTo = null,
        ?int $version = null
    ): Recipe {
        if (! ProductSku::query()->whereKey($skuId)->exists()) {
            throw new InvalidArgumentException("SKU {$skuId} 不存在");
        }

        $items = $this->normalizeItems($items);
        $from = $this->day($effectiveFrom);
        $to = $effectiveTo === null ? null : $this->day($effectiveTo);

        if ($to !== null && $to->lt($from)) {
            throw new InvalidArgumentException('effective_to 不能早于 effective_from');
        }

        return DB::transaction(function () use ($skuId, $items, $from, $to, $version) {
            // 同一 SKU 的版本号/区间串行化，避免并发写入重叠版本
            ProductSku::query()->whereKey($skuId)->lockForUpdate()->first();

            $this->assertNoOverlap($skuId, $from, $to);

            if ($version === null) {
                $version = ((int) Recipe::query()->where('product_sku_id', $skuId)->max('version')) + 1;
            }
            if (Recipe::query()->where('product_sku_id', $skuId)->where('version', $version)->exists()) {
                throw new InvalidArgumentException("SKU {$skuId} 的配方版本 {$version} 已存在");
            }

            $recipe = Recipe::create([
                'product_sku_id' => $skuId,
                'version' => $version,
                'effective_from' => $from->toDateString(),
                'effective_to' => $to?->toDateString(),
                'status' => Recipe::STATUS_DRAFT,
            ]);

            foreach ($items as $item) {
                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'material_id' => $item['material_id'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'loss_rate' => $item['loss_rate'],
                ]);
            }

            return $recipe->load('items');
        });
    }

    /**
     * 激活指定版本；与同 SKU 其他 active 版本生效区间重叠时拒绝。
     */
    public function activate(int $recipeId): Recipe
    {
        return DB::transaction(function () use ($recipeId) {
            $recipe = Recipe::query()->lockForUpdate()->find($recipeId);
            if (! $recipe) {
                throw new InvalidArgumentException("配方 {$recipeId} 不存在");
            }

            if ($recipe->status === Recipe::STATUS_ACTIVE) {
                return $recipe->load('items');
            }

            /** @var Recipe|null $conflict */
            $conflict = $this->overlapQuery(
                $recipe->product_sku_id,
                $recipe->effective_from->copy()->startOfDay(),
                $recipe->effective_to?->copy()->startOfDay()
            )
                ->where('status', Recipe::STATUS_ACTIVE)
                ->where('id', '!=', $recipe->id)
                ->first();

            if ($conflict) {
                throw new RecipeVersionOverlapException(
                    "配方版本 {$recipe->id}（v{$recipe->version}）与已激活版本 {$conflict->id}（v{$conflict->version}）生效区间重叠"
                );
            }

            $recipe->status = Recipe::STATUS_ACTIVE;
            $recipe->save();

            return $recipe->load('items');
        });
    }

    public function archive(int $recipeId): Recipe
    {
        $recipe = Recipe::query()->find($recipeId);
        if (! $recipe) {
            throw new InvalidArgumentException("配方 {$recipeId} 不存在");
        }

        $recipe->status = Recipe::STATUS_ARCHIVED;
        $recipe->save();

        return $recipe->refresh();
    }

    /**
     * 取指定日期生效中的配方：status=active 且日期落在 [effective_from, effective_to]（含端点）。
     */
    public function activeForSku(int $skuId, DateTimeInterface|string|null $date = null): Recipe
    {
        $day = $date === null ? Carbon::today() : $this->day($date);

        $recipe = Recipe::query()
            ->where('product_sku_id', $skuId)
            ->where('status', Recipe::STATUS_ACTIVE)
            ->effectiveOn($day)
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();

        if (! $recipe) {
            throw new NoActiveRecipeException("SKU {$skuId} 在 {$day->toDateString()} 没有生效中的配方版本");
        }

        return $recipe->load('items.material');
    }

    public function versionsForSku(int $skuId): Collection
    {
        return Recipe::query()
            ->where('product_sku_id', $skuId)
            ->orderBy('version')
            ->get();
    }

    public function itemsFor(int $recipeId): Collection
    {
        return RecipeItem::query()->where('recipe_id', $recipeId)->orderBy('id')->get();
    }

    /**
     * 同 SKU 非归档版本中，与本区间重叠的配方（null 的 effective_to 视为无穷远）。
     *
     * @return Builder<Recipe>
     */
    private function overlapQuery(int $skuId, Carbon $from, ?Carbon $to): Builder
    {
        return Recipe::query()
            ->where('product_sku_id', $skuId)
            ->where('status', '!=', Recipe::STATUS_ARCHIVED)
            ->whereDate('effective_from', '<=', $to?->toDateString() ?? '9999-12-31')
            ->where(function (Builder $sub) use ($from) {
                $sub->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString());
            });
    }

    private function assertNoOverlap(int $skuId, Carbon $from, ?Carbon $to): void
    {
        /** @var Recipe|null $conflict */
        $conflict = $this->overlapQuery($skuId, $from, $to)->first();

        if ($conflict) {
            throw new RecipeVersionOverlapException(
                "SKU {$skuId} 的配方版本 {$conflict->id}（v{$conflict->version}）生效区间与 {$from->toDateString()} ~ "
                .($to?->toDateString() ?? '长期').' 重叠'
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{material_id:int, qty:float, unit:?string, loss_rate:float}>
     */
    private function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('配方明细不能为空');
        }

        $normalized = [];
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('配方明细每行必须是数组');
            }

            $materialId = (int) ($item['material_id'] ?? 0);
            if ($materialId <= 0) {
                throw new InvalidArgumentException('配方明细的 material_id 必须为正整数');
            }
            if (isset($seen[$materialId])) {
                throw new InvalidArgumentException("配方明细中原料 {$materialId} 重复");
            }
            if (! Material::query()->whereKey($materialId)->exists()) {
                throw new InvalidArgumentException("原料 {$materialId} 不存在");
            }
            $seen[$materialId] = true;

            if (! isset($item['qty']) || ! is_numeric($item['qty']) || (float) $item['qty'] <= 0) {
                throw new InvalidArgumentException("原料 {$materialId} 的 qty 必须为正数");
            }

            $lossRate = $item['loss_rate'] ?? 0;
            if (! is_numeric($lossRate) || (float) $lossRate < 0 || (float) $lossRate >= 1) {
                throw new InvalidArgumentException("原料 {$materialId} 的 loss_rate 必须在 [0, 1) 区间");
            }

            $unit = isset($item['unit']) && $item['unit'] !== '' ? (string) $item['unit'] : null;

            $normalized[] = [
                'material_id' => $materialId,
                'qty' => round((float) $item['qty'], 3),
                'unit' => $unit,
                'loss_rate' => round((float) $lossRate, 4),
            ];
        }

        return $normalized;
    }

    private function day(DateTimeInterface|string $date): Carbon
    {
        return Carbon::parse($date)->startOfDay();
    }
}
