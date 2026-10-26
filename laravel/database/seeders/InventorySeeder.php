<?php

namespace Database\Seeders;

use App\Domain\Inventory\Enums\BizType;
use App\Domain\Inventory\Enums\ItemType;
use App\Domain\Inventory\Enums\WarehouseType;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * 库存域最小演示数据：1 个中央仓 + 1 个门店仓 + 1 个供应商 + 一笔期初库存。
 * 幂等：重复执行不会重复建仓/建供应商；期初库存只在中央仓无流水时写入。
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $central = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-CENTRAL'],
            [
                'name' => '中央仓',
                'type' => WarehouseType::CENTRAL->value,
                'status' => 'active',
            ]
        );

        Warehouse::query()->firstOrCreate(
            ['code' => 'WH-STORE-001'],
            [
                'name' => '示例门店仓',
                'store_id' => null,
                'type' => WarehouseType::STORE->value,
                'status' => 'active',
            ]
        );

        $supplier = Supplier::query()->firstOrCreate(
            ['code' => 'SUP-001'],
            [
                'name' => '示例原料供应商',
                'status' => 'active',
            ]
        );

        if (StockLedger::query()->where('warehouse_id', $central->id)->exists()) {
            return;
        }

        /** @var StockService $stock */
        $stock = app(StockService::class);

        $stock->receive([
            'warehouse_id' => $central->id,
            'item_type' => ItemType::MATERIAL->value,
            'item_id' => 1,
            'unit' => 'kg',
            'qty' => 100,
            'unit_cost' => 12.50,
            'biz_type' => BizType::RECEIPT->value,
            'biz_id' => 'SEED-OPENING',
            'batch_no' => 'SEED-BATCH-001',
            'production_date' => now()->subDays(3)->toDateString(),
            'expiry_date' => now()->addDays(27)->toDateString(),
            'supplier_id' => $supplier->id,
        ]);
    }
}
