<?php

namespace Tests\Feature\Domain\Inventory;

use App\Domain\Inventory\Exceptions\ConcurrentStockUpdateException;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * 并发扣减：不超卖 + 乐观锁版本冲突保护。
 */
class ConcurrencyTest extends InventoryTestCase
{
    public function test_second_deduction_cannot_oversell(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10);

        $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 6,
            'biz_type' => 'consume',
        ]);

        $rejected = false;

        try {
            // 两个"并发请求"都以为有 10 件：第二次必须被拒绝，不能扣成 -2
            $this->stockService()->issue([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 6,
                'biz_type' => 'consume',
            ]);
        } catch (InsufficientStockException) {
            $rejected = true;
        }

        $this->assertTrue($rejected, '并发第二次扣减未被拒绝，出现超卖');
        $this->assertSame(4.0, $this->stockQty($warehouse->id));
        // 只有期初 + 第一次扣减两条流水
        $this->assertSame(2, StockLedger::query()->count());
    }

    public function test_stale_balance_read_never_writes_negative_stock(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 10);

        // 模拟另一个请求在本次读取前已提交扣减：余额变 4、version +1
        DB::table('stock_items')->where('id', $stock->id)->update(['qty' => 4, 'version' => 1]);

        $rejected = false;

        try {
            $this->stockService()->issue([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 5,
                'biz_type' => 'consume',
            ]);
        } catch (InsufficientStockException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertSame(4.0, $this->stockQty($warehouse->id));
        // 未产生新流水，库存不会因陈旧读被写坏
        $this->assertSame(1, StockLedger::query()->count());
    }

    public function test_optimistic_version_conflict_is_retried_then_throws(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10);

        /** @var StockService $conflictingService */
        $conflictingService = new class extends StockService
        {
            /** 每次写库存行前都模拟被其他事务抢先提交（version +1）。 */
            protected function applyStockMutation(StockItem $stock, array $attributes): StockItem
            {
                StockItem::query()->whereKey($stock->id)->update([
                    'version' => (int) $stock->version + 1,
                ]);

                return parent::applyStockMutation($stock, $attributes);
            }
        };

        $thrown = null;

        try {
            $conflictingService->receive([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 1,
                'unit_cost' => 10.00,
            ]);
        } catch (ConcurrentStockUpdateException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(ConcurrentStockUpdateException::class, $thrown);
        // 重试全部失败后整体回滚：库存余额与流水都保持原状
        $this->assertSame(10.0, $this->stockQty($warehouse->id));
        $this->assertSame(1, StockLedger::query()->count());
    }

    public function test_version_increments_on_every_stock_mutation(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 10);
        $this->assertSame(1, (int) $stock->fresh()->version);

        $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 2,
        ]);
        $this->assertSame(2, (int) $stock->fresh()->version);

        $this->stockService()->waste([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 1,
        ]);
        $this->assertSame(3, (int) $stock->fresh()->version);

        $this->stockService()->adjust([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'target_qty' => 5,
        ]);
        $this->assertSame(4, (int) $stock->fresh()->version);
        $this->assertSame(5.0, $this->stockQty($warehouse->id));
    }

    public function test_failed_mutation_keeps_version_unchanged(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 3);

        try {
            $this->stockService()->issue([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 5,
            ]);
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame(1, (int) $stock->fresh()->version);
        $this->assertSame(3.0, $this->stockQty($warehouse->id));
    }
}
