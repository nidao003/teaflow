<?php

namespace Tests\Feature\Domain\Product;

use App\Domain\Product\Models\Material;
use App\Domain\Product\Models\ProductSku;
use App\Domain\Product\Services\SkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 商品/配方/成本域测试基座：RefreshDatabase（phpunit.xml 已固定 sqlite :memory:）。
 */
abstract class ProductTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * 在现有 products 表插入演示商品，返回 products.id。
     */
    protected function createProduct(array $overrides = []): int
    {
        $now = now()->toDateTimeString();

        return DB::table('products')->insertGetId(array_merge([
            'title' => '测试商品-'.uniqid(),
            'content' => '商品域测试数据',
            'specs' => json_encode([['type' => '默认', 'price' => 1800, 'market_price' => 2200]]),
            'image' => 'https://example.test/product.png',
            'status' => 1,
            'type_id' => 0,
            'user_id' => 0,
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    protected function createSku(int $productId, string $specKey = '大杯/标准糖', array $overrides = []): ProductSku
    {
        /** @var SkuService $skuService */
        $skuService = app(SkuService::class);

        return $skuService->create(array_merge([
            'product_id' => $productId,
            'spec_key' => $specKey,
            'price' => 18.00,
            'market_price' => 22.00,
            'status' => ProductSku::STATUS_ACTIVE,
        ], $overrides));
    }

    protected function createMaterial(string $name, string $unit = 'g', array $overrides = []): Material
    {
        return Material::create(array_merge([
            'name' => $name,
            'unit' => $unit,
            'category' => '测试分类',
            'shelf_life_days' => 30,
            'safety_stock' => 0,
        ], $overrides));
    }

    /**
     * 库存域 stock_items 可能尚未建表（任务并发），测试内按任务约定字段补建一张最小表。
     */
    protected function ensureStockItemsTable(): void
    {
        if (Schema::hasTable('stock_items')) {
            return;
        }

        Schema::create('stock_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->string('item_type', 16);
            $table->unsignedBigInteger('item_id');
            $table->string('unit', 32);
            $table->decimal('qty', 12, 3)->default(0);
            $table->decimal('locked_qty', 12, 3)->default(0);
            $table->decimal('avg_cost', 10, 4)->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_type', 'item_id']);
        });
    }

    /**
     * 写入库存余额行；按实际表字段取交集，兼容库存域自己的 stock_items 结构。
     */
    protected function insertStockItem(int $warehouseId, int $materialId, float $qty, float $avgCost): void
    {
        $this->ensureStockItemsTable();

        $columns = array_flip(Schema::getColumnListing('stock_items'));

        $row = array_intersect_key([
            'warehouse_id' => $warehouseId,
            'item_type' => 'material',
            'item_id' => $materialId,
            'unit' => 'g',
            'qty' => $qty,
            'locked_qty' => 0,
            'avg_cost' => $avgCost,
            'version' => 0,
        ], $columns);

        DB::table('stock_items')->insert($row);
    }
}
