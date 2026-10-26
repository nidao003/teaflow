<?php

namespace Database\Seeders;

use App\Domain\Product\Models\Material;
use App\Domain\Product\Models\ProductSku;
use App\Domain\Product\Services\CostService;
use App\Domain\Product\Services\RecipeService;
use App\Domain\Product\Services\SkuService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 商品/配方/BOM/成本域演示数据。可重复执行（按唯一键 upsert / 已存在则跳过）。
 * 不修改现有 products 表结构，只追加一条演示商品与 SKU。
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, Material> $materials */
        $materials = $this->seedMaterials();

        foreach ($this->seedSkus() as $sku) {
            $this->seedRecipe($sku, $materials);
            $this->seedFirstCostCard($sku, $materials);
        }
    }

    /**
     * @return array<string, Material>
     */
    private function seedMaterials(): array
    {
        $definitions = [
            ['name' => '红茶底', 'unit' => 'g', 'category' => '茶底', 'shelf_life_days' => 365, 'safety_stock' => 2000],
            ['name' => '全脂牛奶', 'unit' => 'ml', 'category' => '乳制品', 'shelf_life_days' => 7, 'safety_stock' => 10000],
            ['name' => '黑糖珍珠', 'unit' => 'g', 'category' => '小料', 'shelf_life_days' => 3, 'safety_stock' => 3000],
            ['name' => '果糖糖浆', 'unit' => 'g', 'category' => '糖浆', 'shelf_life_days' => 180, 'safety_stock' => 5000],
        ];

        $materials = [];
        foreach ($definitions as $definition) {
            $materials[$definition['name']] = Material::query()->updateOrCreate(
                ['name' => $definition['name'], 'unit' => $definition['unit']],
                [
                    'category' => $definition['category'],
                    'shelf_life_days' => $definition['shelf_life_days'],
                    'safety_stock' => $definition['safety_stock'],
                ]
            );
        }

        return $materials;
    }

    /**
     * @return array<int, ProductSku>
     */
    private function seedSkus(): array
    {
        $productId = DB::table('products')->where('title', '招牌珍珠奶茶')->value('id');

        if (! $productId) {
            $now = now()->toDateTimeString();
            $productId = DB::table('products')->insertGetId([
                'title' => '招牌珍珠奶茶',
                'content' => 'ProductSeeder 演示商品：红茶底 + 牛奶 + 珍珠',
                'specs' => json_encode([['type' => '默认', 'price' => 1800, 'market_price' => 2200]]),
                'image' => 'https://example.test/product-demo.png',
                'status' => 1,
                'type_id' => 0,
                'user_id' => 0,
                'sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /** @var SkuService $skuService */
        $skuService = app(SkuService::class);

        $specs = [
            ['spec_key' => '大杯/标准糖', 'price' => 18.00, 'market_price' => 22.00],
            ['spec_key' => '中杯/标准糖', 'price' => 15.00, 'market_price' => 18.00],
        ];

        $skus = [];
        foreach ($specs as $spec) {
            $sku = ProductSku::query()
                ->where('product_id', $productId)
                ->where('spec_key', $spec['spec_key'])
                ->first();

            if (! $sku) {
                $sku = $skuService->create([
                    'product_id' => $productId,
                    'spec_key' => $spec['spec_key'],
                    'price' => $spec['price'],
                    'market_price' => $spec['market_price'],
                    'status' => ProductSku::STATUS_ACTIVE,
                ]);
            }

            $skus[] = $sku;
        }

        return $skus;
    }

    private function seedRecipe(ProductSku $sku, array $materials): void
    {
        /** @var RecipeService $recipeService */
        $recipeService = app(RecipeService::class);

        if ($recipeService->versionsForSku($sku->id)->isNotEmpty()) {
            return;
        }

        $recipe = $recipeService->createVersion(
            $sku->id,
            [
                ['material_id' => $materials['红茶底']->id, 'qty' => 12, 'unit' => 'g', 'loss_rate' => 0],
                ['material_id' => $materials['全脂牛奶']->id, 'qty' => 180, 'unit' => 'ml', 'loss_rate' => 0.05],
                ['material_id' => $materials['黑糖珍珠']->id, 'qty' => 40, 'unit' => 'g', 'loss_rate' => 0.10],
                ['material_id' => $materials['果糖糖浆']->id, 'qty' => 15, 'unit' => 'g', 'loss_rate' => 0],
            ],
            now()->toDateString(),
            null,
            1
        );

        $recipeService->activate($recipe->id);
    }

    private function seedFirstCostCard(ProductSku $sku, array $materials): void
    {
        /** @var CostService $costService */
        $costService = app(CostService::class);

        if ($costService->latest($sku->id)) {
            return;
        }

        // 库存域就绪前用演示 fallback 单价；库存表存在时 CostService 会优先取 stock_items.avg_cost
        $costService->calc($sku->id, now(), [
            'material_costs' => [
                $materials['红茶底']->id => 0.12,
                $materials['全脂牛奶']->id => 0.008,
                $materials['黑糖珍珠']->id => 0.01,
                $materials['果糖糖浆']->id => 0.012,
            ],
        ]);
    }
}
