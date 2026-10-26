<?php

namespace Tests\Feature\Domain\Inventory;

use App\Domain\Inventory\Enums\BizType;
use App\Domain\Inventory\Enums\LedgerDirection;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Models\Wastage;
use Illuminate\Support\Facades\DB;

class StockLedgerTest extends InventoryTestCase
{
    public function test_opening_inbound_outbound_and_waste_reconcile(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 100, 10.00);

        $this->stockService()->receive([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 50,
            'unit_cost' => 12.50,
            'biz_type' => 'purchase',
            'biz_id' => 'PO-001',
        ]);

        $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 30,
            'biz_type' => 'consume',
            'biz_id' => 'ORDER-001',
        ]);

        $wastage = $this->stockService()->waste([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 10,
            'reason' => '过期报废',
            'biz_id' => 'WASTE-001',
        ]);

        // 期初 100 + 入库 50 - 出库 30 - 报损 10 = 110
        $this->assertSame(110.0, (float) $stock->fresh()->qty);
        $this->assertSame(10.83, (float) $stock->fresh()->avg_cost);

        $ledgers = StockLedger::query()->orderBy('id')->get();
        $this->assertCount(4, $ledgers);

        $inboundQty = (float) $ledgers->filter(fn (StockLedger $row): bool => $row->direction === LedgerDirection::IN)->sum('qty');
        $outboundQty = (float) $ledgers->filter(fn (StockLedger $row): bool => $row->direction === LedgerDirection::OUT)->sum('qty');
        $this->assertSame(150.0, $inboundQty);
        $this->assertSame(40.0, $outboundQty);
        $this->assertSame(110.0, (float) $ledgers->last()->balance_after);

        // 报损同时留痕：wastages + waste 流水
        $this->assertSame(10.0, (float) $wastage->qty);
        $this->assertSame(108.30, (float) $wastage->amount);
        $this->assertSame('waste', $ledgers->last()->biz_type->value);

        // 流水链余额连续：每条流水的 balance_after 可追溯
        $running = 0.0;
        foreach ($ledgers as $ledger) {
            $running += $ledger->direction === LedgerDirection::IN ? (float) $ledger->qty : -(float) $ledger->qty;
            $this->assertSame(round($running, 3), (float) $ledger->balance_after);
        }
    }

    public function test_ledger_is_append_only_and_cannot_be_updated(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10);

        $ledger = StockLedger::query()->firstOrFail();

        $this->expectException(\LogicException::class);
        $ledger->update(['qty' => 999]);
    }

    public function test_ledger_is_append_only_and_cannot_be_deleted(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 10);

        $ledger = StockLedger::query()->firstOrFail();

        $this->expectException(\LogicException::class);
        $ledger->delete();
    }

    public function test_negative_stock_issue_is_rejected(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 5);

        try {
            $this->stockService()->issue([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 6,
                'biz_type' => 'consume',
            ]);
            $this->fail('负库存出库未被拒绝');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(6.0, $exception->requested);
            $this->assertSame(5.0, $exception->available);
        }

        $this->assertSame(5.0, (float) $stock->fresh()->qty);
        // 被拒绝时不得写流水
        $this->assertSame(1, StockLedger::query()->count());
    }

    public function test_locked_qty_reduces_available_and_blocks_issue(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 10);

        DB::table('stock_items')->where('id', $stock->id)->update(['locked_qty' => 5]);

        try {
            $this->stockService()->issue([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 6,
            ]);
            $this->fail('锁定量未计入可用库存');
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame(10.0, (float) $stock->fresh()->qty);

        $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 5,
        ]);

        $this->assertSame(5.0, (float) $stock->fresh()->qty);
        // 锁定库存本身不被出库消耗
        $this->assertSame(5.0, (float) $stock->fresh()->locked_qty);
    }

    public function test_weighted_average_cost_is_recalculated_on_receipt(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 10, 10.00);

        $this->stockService()->receive([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 10,
            'unit_cost' => 20.00,
        ]);

        $this->assertSame(15.0, (float) $stock->fresh()->avg_cost);

        $this->stockService()->receive([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 10,
            'unit_cost' => 30.00,
        ]);

        // (10*10 + 10*20 + 10*30) / 30 = 20
        $this->assertSame(20.0, (float) $stock->fresh()->avg_cost);
        $this->assertSame(30.0, (float) $stock->fresh()->qty);
    }

    public function test_adjust_sets_target_qty_and_writes_adjust_ledger(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 10, 10.00);

        $ledger = $this->stockService()->adjust([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'target_qty' => 7,
            'biz_id' => 'ADJ-001',
        ]);

        $this->assertNotNull($ledger);
        $this->assertSame(LedgerDirection::OUT, $ledger->direction);
        $this->assertSame(BizType::ADJUST, $ledger->biz_type);
        $this->assertSame(3.0, (float) $ledger->qty);
        $this->assertSame(7.0, (float) $ledger->balance_after);
        $this->assertSame(7.0, (float) $stock->fresh()->qty);

        // 目标与账面一致时不写流水
        $this->assertNull($this->stockService()->adjust([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'target_qty' => 7,
        ]));

        // 向上调账：数量增加，单价参与加权
        $inbound = $this->stockService()->adjust([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'target_qty' => 12,
            'unit_cost' => 8.00,
        ]);

        $this->assertNotNull($inbound);
        $this->assertSame(LedgerDirection::IN, $inbound->direction);
        $this->assertSame(5.0, (float) $inbound->qty);
        $this->assertSame(12.0, (float) $stock->fresh()->qty);
        // (7*10 + 5*8) / 12 = 9.166… → 9.17
        $this->assertSame(9.17, (float) $stock->fresh()->avg_cost);
    }

    public function test_adjust_below_zero_is_rejected(): void
    {
        $warehouse = $this->makeWarehouse();
        $stock = $this->openingStock($warehouse, 10, 10.00);

        try {
            $this->stockService()->adjust([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'target_qty' => -1,
            ]);
            $this->fail('负库存调账未被拒绝');
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame(10.0, (float) $stock->fresh()->qty);
    }

    public function test_transfer_moves_stock_between_warehouses_in_one_transaction(): void
    {
        $from = $this->makeWarehouse(['name' => '门店A']);
        $to = $this->makeWarehouse(['name' => '门店B']);
        $stock = $this->openingStock($from, 10, 5.00);

        $result = $this->stockService()->transfer([
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 4,
            'biz_id' => 'TRANSFER-001',
        ]);

        $this->assertCount(1, $result['out']);
        $this->assertCount(1, $result['in']);
        $this->assertSame(6.0, (float) $stock->fresh()->qty);
        $this->assertSame(4.0, $this->stockQty($to->id));
        $this->assertSame(5.0, (float) StockItem::query()->where('warehouse_id', $to->id)->firstOrFail()->avg_cost);

        $outLedger = $result['out'][0];
        $inLedger = $result['in'][0];
        $this->assertSame(LedgerDirection::OUT, $outLedger->direction);
        $this->assertSame(LedgerDirection::IN, $inLedger->direction);
        $this->assertSame(BizType::TRANSFER, $outLedger->biz_type);
        $this->assertSame(6.0, (float) $outLedger->balance_after);
        $this->assertSame(4.0, (float) $inLedger->balance_after);
        $this->assertSame('TRANSFER-001', $outLedger->biz_id);
        $this->assertSame('TRANSFER-001', $inLedger->biz_id);
    }

    public function test_unknown_warehouse_or_item_is_rejected(): void
    {
        $warehouse = $this->makeWarehouse();

        $this->expectException(InventoryException::class);

        $this->stockService()->receive([
            'warehouse_id' => 999999,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 1,
        ]);
    }

    public function test_wastage_without_stock_is_rejected_and_not_recorded(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->openingStock($warehouse, 2);

        try {
            $this->stockService()->waste([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 3,
            ]);
            $this->fail('超量报损未被拒绝');
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame(0, Wastage::query()->count());
        $this->assertSame(2.0, $this->stockQty($warehouse->id));
    }
}
