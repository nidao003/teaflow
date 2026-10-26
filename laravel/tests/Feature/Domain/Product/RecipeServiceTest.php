<?php

namespace Tests\Feature\Domain\Product;

use App\Domain\Product\Exceptions\NoActiveRecipeException;
use App\Domain\Product\Exceptions\RecipeVersionOverlapException;
use App\Domain\Product\Models\ProductSku;
use App\Domain\Product\Models\Recipe;
use App\Domain\Product\Services\RecipeService;
use InvalidArgumentException;

class RecipeServiceTest extends ProductTestCase
{
    private RecipeService $recipeService;

    private ProductSku $sku;

    private int $teaId;

    private int $milkId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipeService = app(RecipeService::class);
        $productId = $this->createProduct();
        $this->sku = $this->createSku($productId);
        $this->teaId = $this->createMaterial('红茶底', 'g')->id;
        $this->milkId = $this->createMaterial('牛奶', 'ml')->id;
    }

    public function test_create_version_persists_items_and_increment_version(): void
    {
        $v1 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10, 'unit' => 'g', 'loss_rate' => 0.05],
            ['material_id' => $this->milkId, 'qty' => 180, 'loss_rate' => 0],
        ], '2026-01-01', '2026-01-31');

        $this->assertSame(1, $v1->version);
        $this->assertSame(Recipe::STATUS_DRAFT, $v1->status);
        $this->assertSame('2026-01-01', $v1->effective_from->toDateString());
        $this->assertSame('2026-01-31', $v1->effective_to->toDateString());
        $this->assertCount(2, $v1->items);

        $teaItem = $v1->items->firstWhere('material_id', $this->teaId);
        $this->assertSame('10.000', $teaItem->qty);
        $this->assertSame('0.0500', $teaItem->loss_rate);
        $this->assertEqualsWithDelta(10.5, $teaItem->effectiveQty(), 0.000001);

        $v2 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 12, 'loss_rate' => 0],
        ], '2026-02-01');

        $this->assertSame(2, $v2->version);
        $this->assertNull($v2->effective_to);
        $this->assertCount(2, $this->recipeService->versionsForSku($this->sku->id));
    }

    public function test_explicit_duplicate_version_rejected(): void
    {
        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01', '2026-01-31', 1);

        $this->expectException(InvalidArgumentException::class);

        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-03-01', '2026-03-31', 1);
    }

    public function test_overlapping_version_rejected(): void
    {
        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01', '2026-03-31');

        // 边界日 2026-03-31 也属于重叠区间
        $this->expectException(RecipeVersionOverlapException::class);

        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 12],
        ], '2026-03-31', '2026-06-30');
    }

    public function test_open_ended_version_overlaps_everything_after_it(): void
    {
        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01');

        $this->expectException(RecipeVersionOverlapException::class);

        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 12],
        ], '2026-06-01', '2026-06-30');
    }

    public function test_adjacent_versions_allowed(): void
    {
        $v1 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01', '2026-01-31');

        $v2 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 12],
        ], '2026-02-01', '2026-02-28');

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);
    }

    public function test_archived_version_does_not_block_new_version(): void
    {
        $v1 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01', '2026-12-31');

        $this->recipeService->archive($v1->id);

        $v2 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 12],
        ], '2026-06-01', '2026-06-30');

        $this->assertSame(2, $v2->version);
    }

    public function test_active_for_sku_selects_version_by_effective_date(): void
    {
        $v1 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01', '2026-01-31');
        $v2 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 12],
        ], '2026-02-01');

        $this->recipeService->activate($v1->id);
        $this->recipeService->activate($v2->id);

        $this->assertSame($v1->id, $this->recipeService->activeForSku($this->sku->id, '2026-01-01')->id);
        $this->assertSame($v1->id, $this->recipeService->activeForSku($this->sku->id, '2026-01-31')->id);
        $this->assertSame($v2->id, $this->recipeService->activeForSku($this->sku->id, '2026-02-01')->id);
        $this->assertSame($v2->id, $this->recipeService->activeForSku($this->sku->id, '2026-12-31')->id);
    }

    public function test_active_for_sku_without_active_recipe_throws(): void
    {
        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01', '2026-01-31');

        $this->expectException(NoActiveRecipeException::class);

        $this->recipeService->activeForSku($this->sku->id, '2026-02-15');
    }

    public function test_reactivating_archived_version_rejected_when_overlaps_active(): void
    {
        $v1 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10],
        ], '2026-01-01', '2026-06-30');
        $this->recipeService->activate($v1->id);
        $this->recipeService->archive($v1->id);

        $v2 = $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 12],
        ], '2026-06-01', '2026-12-31');
        $this->recipeService->activate($v2->id);

        $this->expectException(RecipeVersionOverlapException::class);

        $this->recipeService->activate($v1->id);
    }

    public function test_empty_items_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->recipeService->createVersion($this->sku->id, [], '2026-01-01');
    }

    public function test_unknown_material_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => 9999, 'qty' => 10],
        ], '2026-01-01');
    }

    public function test_invalid_qty_and_loss_rate_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->recipeService->createVersion($this->sku->id, [
            ['material_id' => $this->teaId, 'qty' => 10, 'loss_rate' => 1],
        ], '2026-01-01');
    }
}
