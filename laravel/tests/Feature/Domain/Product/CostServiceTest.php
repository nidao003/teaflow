<?php

namespace Tests\Feature\Domain\Product;

use App\Domain\Product\Exceptions\MaterialCostUnavailableException;
use App\Domain\Product\Exceptions\NoActiveRecipeException;
use App\Domain\Product\Models\CostCard;
use App\Domain\Product\Models\ProductSku;
use App\Domain\Product\Models\Recipe;
use App\Domain\Product\Services\CostService;
use App\Domain\Product\Services\RecipeService;

class CostServiceTest extends ProductTestCase
{
    private CostService $costService;

    private RecipeService $recipeService;

    private ProductSku $sku;

    private int $teaId;

    private int $milkId;

    private int $pearlId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->costService = app(CostService::class);
        $this->recipeService = app(RecipeService::class);

        $productId = $this->createProduct();
        $this->sku = $this->createSku($productId);
        $this->teaId = $this->createMaterial('红茶底', 'g')->id;
        $this->milkId = $this->createMaterial('牛奶', 'ml')->id;
        $this->pearlId = $this->createMaterial('黑糖珍珠', 'g')->id;
    }

    public function test_bom_cost_includes_loss_rate(): void
    {
        $recipe = $this->createActiveRecipe([
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0],
            ['material_id' => $this->milkId, 'qty' => 200, 'unit' => 'ml', 'loss_rate' => 0.05],
            ['material_id' => $this->pearlId, 'qty' => 20, 'unit' => 'g', 'loss_rate' => 0.10],
        ], '2026-01-01', '2026-01-31');

        // 10×1×0.10 = 1.00；200×1.05×0.02 = 4.20；20×1.10×0.05 = 1.10
        $card = $this->costService->calc($this->sku->id, '2026-01-15', [
            'material_costs' => [
                $this->teaId => 0.10,
                $this->milkId => 0.02,
                $this->pearlId => 0.05,
            ],
        ]);

        $this->assertSame('6.30', $card->unit_cost);
        $this->assertSame(1, $card->version);
        $this->assertSame($recipe->id, $card->recipe_id);
        $this->assertSame(1, $card->snapshot_json['recipe_version']);
        $this->assertSame('2026-01-15', $card->snapshot_json['calc_date']);
        $this->assertCount(3, $card->snapshot_json['items']);

        $milkItem = $this->snapshotItem($card, $this->milkId);
        $this->assertSame('fallback', $milkItem['cost_source']);
        $this->assertEqualsWithDelta(210.0, $milkItem['effective_qty'], 0.000001);
        $this->assertEqualsWithDelta(4.20, $milkItem['line_cost'], 0.000001);

        // 成本卡落库，SKU 最近成本同步
        $this->assertDatabaseHas('cost_cards', [
            'id' => $card->id,
            'product_sku_id' => $this->sku->id,
            'version' => 1,
        ]);
        $this->assertSame('6.30', ProductSku::query()->findOrFail($this->sku->id)->cost);
    }

    public function test_material_cost_prefers_stock_items_weighted_average(): void
    {
        $this->createActiveRecipe([
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0],
        ], '2026-01-01');

        // 两个仓库存量加权：(100×0.10 + 300×0.06) / 400 = 0.07
        $this->insertStockItem(1, $this->teaId, 100, 0.10);
        $this->insertStockItem(2, $this->teaId, 300, 0.06);

        $card = $this->costService->calc($this->sku->id, '2026-01-15', [
            'material_costs' => [$this->teaId => 0.99],
        ]);

        $this->assertSame('0.70', $card->unit_cost);

        $teaItem = $this->snapshotItem($card, $this->teaId);
        $this->assertSame(CostService::SOURCE_STOCK_ITEMS, $teaItem['cost_source']);
        $this->assertEqualsWithDelta(0.07, $teaItem['unit_cost'], 0.000001);
    }

    public function test_material_cost_falls_back_to_previous_cost_card_snapshot(): void
    {
        $this->createActiveRecipe([
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0],
        ], '2026-01-01');

        $first = $this->costService->calc($this->sku->id, '2026-01-15', [
            'material_costs' => [$this->teaId => 0.10],
        ]);
        $this->assertSame('1.00', $first->unit_cost);

        // 不传 fallback：从上一张成本卡快照取同原料单价
        $second = $this->costService->calc($this->sku->id, '2026-01-20');

        $this->assertSame('1.00', $second->unit_cost);
        $this->assertSame(2, $second->version);
        $teaItem = $this->snapshotItem($second, $this->teaId);
        $this->assertSame(CostService::SOURCE_COST_CARD, $teaItem['cost_source']);
    }

    public function test_changing_recipe_does_not_alter_historical_cost_card(): void
    {
        $this->createActiveRecipe([
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0],
        ], '2026-01-01', '2026-01-31');

        $historical = $this->costService->calc($this->sku->id, '2026-01-15', [
            'material_costs' => [$this->teaId => 0.10],
        ]);
        $this->assertSame('1.00', $historical->unit_cost);

        // 改配方：新版本用量翻倍并加 50% 损耗（30×0.10 = 3.00）
        $v2 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 20, 'unit' => 'g', 'loss_rate' => 0.50],
        ], '2026-02-01');
        $this->recipeService->activate($v2->id);

        $current = $this->costService->calc($this->sku->id, '2026-02-10', [
            'material_costs' => [$this->teaId => 0.10],
        ]);

        $this->assertSame('3.00', $current->unit_cost);
        $this->assertSame(2, $current->version);
        $this->assertSame(2, $current->snapshot_json['recipe_version']);

        // 历史成本卡不可被改写
        $historical->refresh();
        $this->assertSame('1.00', $historical->unit_cost);
        $this->assertSame(1, $historical->snapshot_json['recipe_version']);
        $this->assertEqualsWithDelta(10.0, $this->snapshotItem($historical, $this->teaId)['qty'], 0.000001);
        $this->assertSame(2, $this->costService->history($this->sku->id)->count());
    }

    public function test_calc_without_active_recipe_throws(): void
    {
        // 只有 draft 版本，未激活
        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0],
        ], '2026-01-01', '2026-01-31');

        $this->expectException(NoActiveRecipeException::class);

        $this->costService->calc($this->sku->id, '2026-01-15', [
            'material_costs' => [$this->teaId => 0.10],
        ]);
    }

    public function test_calc_without_any_material_cost_throws(): void
    {
        $this->createActiveRecipe([
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0],
        ], '2026-01-01');

        $this->expectException(MaterialCostUnavailableException::class);

        $this->costService->calc($this->sku->id, '2026-01-15');
    }

    public function test_recalculating_same_date_creates_new_version(): void
    {
        $this->createActiveRecipe([
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0],
        ], '2026-01-01');

        $fallback = ['material_costs' => [$this->teaId => 0.10]];

        $first = $this->costService->calc($this->sku->id, '2026-01-15', $fallback);
        $second = $this->costService->calc($this->sku->id, '2026-01-15', $fallback);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('1.00', $second->unit_cost);
    }

    private function createActiveRecipe(array $items, string $effectiveFrom, ?string $effectiveTo = null): Recipe
    {
        $recipe = $this->recipeService->createVersion($this->sku->id, $items, $effectiveFrom, $effectiveTo);
        $this->recipeService->activate($recipe->id);

        return $recipe;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotItem(CostCard $card, int $materialId): array
    {
        foreach ($card->snapshot_json['items'] as $item) {
            if ((int) $item['material_id'] === $materialId) {
                return $item;
            }
        }

        $this->fail("成本卡快照中没有原料 {$materialId}");
    }
}
