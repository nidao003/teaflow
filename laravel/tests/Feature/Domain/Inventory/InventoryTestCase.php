<?php

namespace Tests\Feature\Domain\Inventory;

use App\Domain\Inventory\Enums\ItemType;
use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 库存域测试基类。
 *
 * 默认强制 sqlite 内存库，避免测试依赖本机 MySQL；若外部（phpunit.xml / shell）
 * 显式提供 DB_CONNECTION 则尊重外部配置。
 */
abstract class InventoryTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->configureTestingDatabase();
        parent::setUp();
    }

    private function configureTestingDatabase(): void
    {
        $explicit = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION');

        if (is_string($explicit) && $explicit !== '') {
            return;
        }

        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', ':memory:');
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    protected function stockService(): StockService
    {
        return app(StockService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeWarehouse(array $attributes = []): Warehouse
    {
        return Warehouse::create(array_merge([
            'code' => 'WH-'.uniqid(),
            'name' => '测试仓',
            'type' => 'store',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeSupplier(array $attributes = []): Supplier
    {
        return Supplier::create(array_merge([
            'code' => 'SUP-'.uniqid(),
            'name' => '测试供应商',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * 期初库存：通过 StockService 入库（库存唯一出口），biz_type=receipt。
     */
    protected function openingStock(
        Warehouse $warehouse,
        float $qty,
        float $unitCost = 10.00,
        int $itemId = 1,
        string $itemType = ItemType::MATERIAL->value,
        string $unit = 'kg'
    ): StockItem {
        $this->stockService()->receive([
            'warehouse_id' => $warehouse->id,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'unit' => $unit,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'biz_type' => 'receipt',
            'biz_id' => 'OPENING',
        ]);

        return StockItem::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->firstOrFail();
    }

    /**
     * 直接建库存行（仅用于需要预置特定 qty/version 的用例）。
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeStockItem(Warehouse $warehouse, float $qty, float $avgCost = 10.00, int $itemId = 1, array $attributes = []): StockItem
    {
        return StockItem::create(array_merge([
            'warehouse_id' => $warehouse->id,
            'item_type' => ItemType::MATERIAL->value,
            'item_id' => $itemId,
            'unit' => 'kg',
            'qty' => $qty,
            'locked_qty' => 0,
            'avg_cost' => $avgCost,
            'version' => 0,
        ], $attributes));
    }

    protected function stockQty(int $warehouseId, int $itemId = 1, string $itemType = ItemType::MATERIAL->value): float
    {
        $stock = StockItem::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();

        return $stock === null ? 0.0 : (float) $stock->qty;
    }
}
