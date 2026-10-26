<?php

namespace Tests\Feature\Domain\Inventory;

use App\Domain\Inventory\Enums\LedgerDirection;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockLedger;

class StockFifoTest extends InventoryTestCase
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function receiveBatch(
        int $warehouseId,
        float $qty,
        float $unitCost,
        ?string $batchNo = null,
        ?string $expiryDate = null,
        ?string $productionDate = null,
        int $itemId = 1
    ): StockLedger {
        return $this->stockService()->receive(array_merge([
            'warehouse_id' => $warehouseId,
            'item_type' => 'material',
            'item_id' => $itemId,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'biz_type' => 'receipt',
            'biz_id' => 'OPENING',
            'batch_no' => $batchNo,
            'expiry_date' => $expiryDate,
            'production_date' => $productionDate,
        ]));
    }

    public function test_issue_consumes_earliest_expiry_batch_first(): void
    {
        $warehouse = $this->makeWarehouse();

        $late = $this->receiveBatch($warehouse->id, 10, 10.00, 'LATE', '2027-12-31', '2027-12-01');
        $early = $this->receiveBatch($warehouse->id, 10, 20.00, 'EARLY', '2026-11-30', '2026-11-01');

        $ledgers = $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 5,
            'biz_type' => 'consume',
        ]);

        $this->assertCount(1, $ledgers);
        $this->assertSame($early->batch_id, $ledgers[0]->batch_id);
        $this->assertSame(20.0, (float) $ledgers[0]->unit_cost);
        $this->assertSame(100.0, (float) $ledgers[0]->amount);
        $this->assertSame(15.0, (float) $ledgers[0]->balance_after);

        $this->assertSame(5.0, (float) Batch::query()->where('batch_no', 'EARLY')->firstOrFail()->qty);
        $this->assertSame(10.0, (float) Batch::query()->where('batch_no', 'LATE')->firstOrFail()->qty);
        $this->assertSame(15.0, $this->stockQty($warehouse->id));
    }

    public function test_issue_spans_multiple_batches_in_fifo_order(): void
    {
        $warehouse = $this->makeWarehouse();

        $first = $this->receiveBatch($warehouse->id, 4, 20.00, 'B1', '2026-11-30');
        $second = $this->receiveBatch($warehouse->id, 6, 15.00, 'B2', '2026-12-15');
        $this->receiveBatch($warehouse->id, 10, 10.00, 'B3', '2027-01-15');

        $ledgers = $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 8,
        ]);

        $this->assertCount(2, $ledgers);

        $this->assertSame($first->batch_id, $ledgers[0]->batch_id);
        $this->assertSame(4.0, (float) $ledgers[0]->qty);
        $this->assertSame(20.0, (float) $ledgers[0]->unit_cost);
        $this->assertSame(16.0, (float) $ledgers[0]->balance_after);

        $this->assertSame($second->batch_id, $ledgers[1]->batch_id);
        $this->assertSame(4.0, (float) $ledgers[1]->qty);
        $this->assertSame(15.0, (float) $ledgers[1]->unit_cost);
        $this->assertSame(12.0, (float) $ledgers[1]->balance_after);

        $this->assertSame(0.0, (float) Batch::query()->where('batch_no', 'B1')->firstOrFail()->qty);
        $this->assertSame('exhausted', Batch::query()->where('batch_no', 'B1')->firstOrFail()->status);
        $this->assertSame(2.0, (float) Batch::query()->where('batch_no', 'B2')->firstOrFail()->qty);
        $this->assertSame(10.0, (float) Batch::query()->where('batch_no', 'B3')->firstOrFail()->qty);
        $this->assertSame(12.0, $this->stockQty($warehouse->id));
    }

    public function test_batch_without_expiry_is_consumed_last(): void
    {
        $warehouse = $this->makeWarehouse();

        $noExpiry = $this->receiveBatch($warehouse->id, 5, 5.00, 'NO-EXPIRY', null);
        $withExpiry = $this->receiveBatch($warehouse->id, 5, 7.00, 'HAS-EXPIRY', '2027-06-30');

        $ledgers = $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 5,
        ]);

        $this->assertCount(1, $ledgers);
        $this->assertSame($withExpiry->batch_id, $ledgers[0]->batch_id);
        $this->assertSame(7.0, (float) $ledgers[0]->unit_cost);
        // 先到期批次被扣光，无到期日的批次保留
        $this->assertSame(0.0, (float) Batch::query()->where('batch_no', 'HAS-EXPIRY')->firstOrFail()->qty);
        $this->assertSame(5.0, (float) Batch::query()->where('batch_no', 'NO-EXPIRY')->firstOrFail()->qty);
        $this->assertNotSame($noExpiry->batch_id, $ledgers[0]->batch_id);
    }

    public function test_explicit_batch_issue_is_rejected_when_batch_is_short(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->receiveBatch($warehouse->id, 3, 10.00, 'SHORT', '2026-11-30');
        $this->receiveBatch($warehouse->id, 10, 10.00, 'PLENTY', '2026-12-31');

        $short = Batch::query()->where('batch_no', 'SHORT')->firstOrFail();

        try {
            $this->stockService()->issue([
                'warehouse_id' => $warehouse->id,
                'item_type' => 'material',
                'item_id' => 1,
                'qty' => 5,
                'batch_id' => $short->id,
            ]);
            $this->fail('指定批次库存不足未被拒绝');
        } catch (InsufficientStockException) {
            // expected
        }

        // 事务回滚：批次和库存余额都不变
        $this->assertSame(3.0, (float) Batch::query()->where('batch_no', 'SHORT')->firstOrFail()->qty);
        $this->assertSame(10.0, (float) Batch::query()->where('batch_no', 'PLENTY')->firstOrFail()->qty);
        $this->assertSame(13.0, $this->stockQty($warehouse->id));
    }

    public function test_issue_falls_back_to_average_cost_when_batches_are_short(): void
    {
        $warehouse = $this->makeWarehouse();
        $this->receiveBatch($warehouse->id, 2, 20.00, 'ONLY-BATCH', '2026-12-31');

        // 无批次入库 8 件 @10，平均成本 = (2*20 + 8*10) / 10 = 12
        $this->stockService()->receive([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 8,
            'unit_cost' => 10.00,
        ]);

        $ledgers = $this->stockService()->issue([
            'warehouse_id' => $warehouse->id,
            'item_type' => 'material',
            'item_id' => 1,
            'qty' => 5,
        ]);

        $this->assertCount(2, $ledgers);
        $this->assertSame(2.0, (float) $ledgers[0]->qty);
        $this->assertSame(20.0, (float) $ledgers[0]->unit_cost);
        $this->assertNotNull($ledgers[0]->batch_id);

        $this->assertSame(3.0, (float) $ledgers[1]->qty);
        $this->assertSame(12.0, (float) $ledgers[1]->unit_cost);
        $this->assertNull($ledgers[1]->batch_id);
        $this->assertSame(36.0, (float) $ledgers[1]->amount);

        $this->assertSame(5.0, $this->stockQty($warehouse->id));
        $this->assertSame(5.0, (float) StockLedger::query()->where('warehouse_id', $warehouse->id)->latest('id')->firstOrFail()->balance_after);
        // 出库流水都是 out 方向
        $this->assertSame(
            2,
            StockLedger::query()->where('direction', LedgerDirection::OUT->value)->count()
        );
    }
}
