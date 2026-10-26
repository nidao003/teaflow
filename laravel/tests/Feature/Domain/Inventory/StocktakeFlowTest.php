<?php

namespace Tests\Feature\Domain\Inventory;

use App\Domain\Inventory\Enums\StocktakeStatus;
use App\Domain\Inventory\Exceptions\InvalidStateTransitionException;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Models\StocktakeItem;
use App\Domain\Inventory\Services\StocktakeService;
use Illuminate\Support\Facades\DB;

class StocktakeFlowTest extends InventoryTestCase
{
    private function stocktakeService(): StocktakeService
    {
        return app(StocktakeService::class);
    }

    public function test_create_stocktake_snapshots_book_qty(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10, 10.00, 1);
        $this->openingStock($warehouse, 20, 20.00, 2);

        $stocktake = $this->stocktakeService()->create([
            'warehouse_id' => $warehouse->id,
            'operator_id' => 9,
            'remark' => '月末盘点',
        ]);

        $this->assertSame(StocktakeStatus::COUNTING, $stocktake->status);
        $this->assertSame(2, $stocktake->total_items);
        $this->assertNotEmpty($stocktake->stocktake_no);
        $this->assertStringStartsWith('ST', $stocktake->stocktake_no);

        $bookQty = $stocktake->items->keyBy('item_id');
        $this->assertSame(10.0, (float) $bookQty[1]->book_qty);
        $this->assertSame(20.0, (float) $bookQty[2]->book_qty);
        $this->assertSame(10.0, (float) $bookQty[1]->unit_cost);
        $this->assertNull($bookQty[1]->counted_qty);
        $this->assertSame(0.0, (float) $bookQty[1]->diff_qty);
    }

    public function test_count_and_adjust_writes_ledger_and_updates_stock(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10, 10.00, 1);
        $this->openingStock($warehouse, 20, 20.00, 2);

        $stocktake = $this->stocktakeService()->create(['warehouse_id' => $warehouse->id]);
        $items = $stocktake->items->keyBy('item_id');

        $stocktake = $this->stocktakeService()->count($stocktake, [
            $items[1]->id => 8,
            $items[2]->id => 25,
        ], 9);

        $this->assertSame(StocktakeStatus::COUNTED, $stocktake->status);
        $this->assertNotNull($stocktake->counted_at);

        $countedItems = $stocktake->items->keyBy('item_id');
        $this->assertSame(8.0, (float) $countedItems[1]->counted_qty);
        $this->assertSame(-2.0, (float) $countedItems[1]->diff_qty);
        $this->assertSame(-20.0, (float) $countedItems[1]->diff_amount);
        $this->assertSame(5.0, (float) $countedItems[2]->diff_qty);
        $this->assertSame(100.0, (float) $countedItems[2]->diff_amount);

        $stocktake = $this->stocktakeService()->adjust($stocktake, 9);

        $this->assertSame(StocktakeStatus::COMPLETED, $stocktake->status);
        $this->assertSame(2, $stocktake->diff_items);
        $this->assertSame(80.0, (float) $stocktake->diff_amount);
        $this->assertNotNull($stocktake->completed_at);
        $this->assertTrue($stocktake->items->every(fn (StocktakeItem $item): bool => $item->adjusted));

        $this->assertSame(8.0, $this->stockQty($warehouse->id, 1));
        $this->assertSame(25.0, $this->stockQty($warehouse->id, 2));
        $this->assertSame(25.0, (float) DB::table('stock_items')->where('item_id', 2)->value('qty'));

        $adjustLedgers = StockLedger::query()
            ->where('biz_type', 'adjust')
            ->where('biz_id', $stocktake->stocktake_no)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $adjustLedgers);
        $this->assertSame('out', $adjustLedgers[0]->direction->value);
        $this->assertSame(2.0, (float) $adjustLedgers[0]->qty);
        $this->assertSame(8.0, (float) $adjustLedgers[0]->balance_after);
        $this->assertSame('in', $adjustLedgers[1]->direction->value);
        $this->assertSame(5.0, (float) $adjustLedgers[1]->qty);
        $this->assertSame(25.0, (float) $adjustLedgers[1]->balance_after);
    }

    public function test_count_without_diff_adjusts_nothing_but_completes(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10, 10.00, 1);

        $stocktake = $this->stocktakeService()->create(['warehouse_id' => $warehouse->id]);
        $item = $stocktake->items->first();

        $stocktake = $this->stocktakeService()->count($stocktake, [$item->id => 10]);
        $stocktake = $this->stocktakeService()->adjust($stocktake);

        $this->assertSame(StocktakeStatus::COMPLETED, $stocktake->status);
        $this->assertSame(0, $stocktake->diff_items);
        $this->assertSame(0.0, (float) $stocktake->diff_amount);
        $this->assertSame(10.0, $this->stockQty($warehouse->id));
        // 只有期初那一条流水
        $this->assertSame(1, StockLedger::query()->count());
    }

    public function test_adjust_requires_counted_status(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10, 10.00, 1);

        $stocktake = $this->stocktakeService()->create(['warehouse_id' => $warehouse->id]);

        $this->expectException(InvalidStateTransitionException::class);

        $this->stocktakeService()->adjust($stocktake);
    }

    public function test_count_rejects_negative_qty(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10, 10.00, 1);

        $stocktake = $this->stocktakeService()->create(['warehouse_id' => $warehouse->id]);
        $item = $stocktake->items->first();

        try {
            $this->stocktakeService()->count($stocktake, [$item->id => -1]);
            $this->fail('负实盘数未被拒绝');
        } catch (InventoryException) {
            // expected
        }

        $this->assertSame(StocktakeStatus::COUNTING, $stocktake->fresh()->status);
        $this->assertNull($stocktake->fresh()->items->first()->counted_qty);
    }

    public function test_cancelled_stocktake_cannot_be_adjusted(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10, 10.00, 1);

        $stocktake = $this->stocktakeService()->create(['warehouse_id' => $warehouse->id]);
        $stocktake = $this->stocktakeService()->cancel($stocktake);

        $this->assertSame(StocktakeStatus::CANCELLED, $stocktake->status);

        $this->expectException(InvalidStateTransitionException::class);
        $this->stocktakeService()->adjust($stocktake);
    }

    public function test_stocktake_can_include_item_without_stock_row(): void
    {
        $warehouse = $this->makeWarehouse();

        $stocktake = $this->stocktakeService()->create([
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['item_type' => 'material', 'item_id' => 99, 'unit' => 'kg', 'unit_cost' => 5.00],
            ],
        ]);

        $item = $stocktake->items->first();
        $this->assertSame(0.0, (float) $item->book_qty);
        $this->assertSame(1, $stocktake->total_items);

        $stocktake = $this->stocktakeService()->count($stocktake, [$item->id => 3]);
        $stocktake = $this->stocktakeService()->adjust($stocktake);

        $this->assertSame(StocktakeStatus::COMPLETED, $stocktake->status);
        $this->assertSame(1, $stocktake->diff_items);
        $this->assertSame(15.0, (float) $stocktake->diff_amount);
        $this->assertSame(3.0, $this->stockQty($warehouse->id, 99));
        $this->assertSame(5.0, (float) DB::table('stock_items')->where('item_id', 99)->value('avg_cost'));
    }

    public function test_partial_count_only_adjusts_counted_items(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10, 10.00, 1);
        $this->openingStock($warehouse, 20, 20.00, 2);

        $stocktake = $this->stocktakeService()->create(['warehouse_id' => $warehouse->id]);
        $items = $stocktake->items->keyBy('item_id');

        $stocktake = $this->stocktakeService()->count($stocktake, [$items[1]->id => 7]);
        $stocktake = $this->stocktakeService()->adjust($stocktake);

        $this->assertSame(1, $stocktake->diff_items);
        $this->assertSame(7.0, $this->stockQty($warehouse->id, 1));
        // 未盘点的 item2 不受影响
        $this->assertSame(20.0, $this->stockQty($warehouse->id, 2));
    }
}
