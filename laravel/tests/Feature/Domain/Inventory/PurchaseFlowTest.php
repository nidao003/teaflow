<?php

namespace Tests\Feature\Domain\Inventory;

use App\Domain\Inventory\Enums\BizType;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Exceptions\InvalidStateTransitionException;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\PurchaseOrderItem;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\PurchaseService;

class PurchaseFlowTest extends InventoryTestCase
{
    private function purchaseService(): PurchaseService
    {
        return app(PurchaseService::class);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     */
    private function createOrder(Warehouse $warehouse, ?array $items = null): PurchaseOrder
    {
        $supplier = $this->makeSupplier();

        return $this->purchaseService()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'store_id' => 1,
            'operator_id' => 9,
            'expected_at' => '2026-11-01',
            'items' => $items ?? [
                ['item_id' => 1, 'item_type' => 'material', 'unit' => 'kg', 'qty' => 10, 'unit_price' => 3.50],
                ['item_id' => 2, 'item_type' => 'material', 'unit' => 'kg', 'qty' => 5, 'unit_price' => 10.00],
            ],
        ]);
    }

    public function test_create_purchase_order_computes_totals_and_starts_as_draft(): void
    {
        $warehouse = $this->makeWarehouse();
        $order = $this->createOrder($warehouse);

        $this->assertSame(PurchaseOrderStatus::DRAFT, $order->status);
        $this->assertSame(15.0, (float) $order->total_qty);
        $this->assertSame(85.0, (float) $order->total_amount);
        $this->assertCount(2, $order->items);

        $first = $order->items->firstWhere('item_id', 1);
        $this->assertInstanceOf(PurchaseOrderItem::class, $first);
        $this->assertSame(35.0, (float) $first->amount);
        $this->assertSame(0.0, (float) $first->received_qty);
        $this->assertNotEmpty($order->order_no);
        $this->assertStringStartsWith('PO', $order->order_no);
    }

    public function test_approve_then_partial_and_full_receive_updates_stock_and_status(): void
    {
        $warehouse = $this->makeWarehouse();
        $order = $this->createOrder($warehouse);

        $order = $this->purchaseService()->approve($order, 9);
        $this->assertSame(PurchaseOrderStatus::APPROVED, $order->status);
        $this->assertNotNull($order->approved_at);

        $item1 = $order->items->firstWhere('item_id', 1);
        $item2 = $order->items->firstWhere('item_id', 2);

        // 第一次：只收 item1 的 6 件 → partial_received
        $receipt = $this->purchaseService()->receive($order, [
            ['purchase_order_item_id' => $item1->id, 'qty' => 6],
        ], 9);

        $this->assertSame(6.0, (float) $receipt->received_qty);
        $this->assertSame(21.0, (float) $receipt->total_amount);
        $this->assertSame(PurchaseOrderStatus::PARTIAL_RECEIVED, $order->fresh()->status);
        $this->assertSame(6.0, $this->stockQty($warehouse->id, 1));

        $ledger = StockLedger::query()->where('biz_type', BizType::PURCHASE->value)->firstOrFail();
        $this->assertSame('in', $ledger->direction->value);
        $this->assertSame($order->order_no, $ledger->biz_id);
        $this->assertSame(6.0, (float) $ledger->balance_after);
        $this->assertSame(3.5, (float) $ledger->unit_cost);

        // 第二次：收 item1 剩余 4 + item2 全部 5 → received
        $this->purchaseService()->receive($order, [
            ['purchase_order_item_id' => $item1->id, 'qty' => 4],
            ['purchase_order_item_id' => $item2->id, 'qty' => 5],
        ], 9);

        $this->assertSame(PurchaseOrderStatus::RECEIVED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->received_at);
        $this->assertSame(10.0, $this->stockQty($warehouse->id, 1));
        $this->assertSame(5.0, $this->stockQty($warehouse->id, 2));
        $this->assertSame(2, $order->receipts()->count());
        $this->assertSame(3, StockLedger::query()->where('biz_type', BizType::PURCHASE->value)->count());

        // 收货完成后关闭
        $closed = $this->purchaseService()->close($order->fresh());
        $this->assertSame(PurchaseOrderStatus::CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_receive_requires_approved_status(): void
    {
        $warehouse = $this->makeWarehouse();
        $order = $this->createOrder($warehouse);
        $item = $order->items->first();

        $this->expectException(InvalidStateTransitionException::class);

        $this->purchaseService()->receive($order, [
            ['purchase_order_item_id' => $item->id, 'qty' => 1],
        ]);
    }

    public function test_receive_more_than_ordered_is_rejected(): void
    {
        $warehouse = $this->makeWarehouse();
        $order = $this->purchaseService()->approve($this->createOrder($warehouse));
        $item = $order->items->firstWhere('item_id', 1);

        try {
            $this->purchaseService()->receive($order, [
                ['purchase_order_item_id' => $item->id, 'qty' => 11],
            ]);
            $this->fail('超量收货未被拒绝');
        } catch (InventoryException $exception) {
            $this->assertStringContainsString('超过未收数量', $exception->getMessage());
        }

        $this->assertSame(PurchaseOrderStatus::APPROVED, $order->fresh()->status);
        $this->assertSame(0.0, $this->stockQty($warehouse->id, 1));
        $this->assertSame(0, StockLedger::query()->count());
        $this->assertSame(0, $order->receipts()->count());
    }

    public function test_cancel_allowed_before_receipt_only(): void
    {
        $warehouse = $this->makeWarehouse();

        $draft = $this->createOrder($warehouse);
        $this->assertSame(PurchaseOrderStatus::CANCELLED, $this->purchaseService()->cancel($draft)->status);

        $approved = $this->purchaseService()->approve($this->createOrder($warehouse));
        $this->assertSame(PurchaseOrderStatus::CANCELLED, $this->purchaseService()->cancel($approved)->status);

        $receivedBefore = $this->purchaseService()->approve($this->createOrder($warehouse));
        $item = $receivedBefore->items->first();
        $this->purchaseService()->receive($receivedBefore, [
            ['purchase_order_item_id' => $item->id, 'qty' => 1],
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->purchaseService()->cancel($receivedBefore->fresh());
    }

    public function test_receive_creates_batch_when_expiry_date_is_provided(): void
    {
        $warehouse = $this->makeWarehouse();
        $order = $this->createOrder($warehouse, [
            [
                'item_id' => 1,
                'item_type' => 'material',
                'unit' => 'kg',
                'qty' => 5,
                'unit_price' => 8.00,
                'production_date' => '2026-10-20',
                'expiry_date' => '2026-12-31',
            ],
        ]);

        $order = $this->purchaseService()->approve($order);
        $item = $order->items->first();

        $this->purchaseService()->receive($order, [
            [
                'purchase_order_item_id' => $item->id,
                'qty' => 5,
                'batch_no' => 'BATCH-PO-001',
            ],
        ]);

        $batch = Batch::query()->where('batch_no', 'BATCH-PO-001')->firstOrFail();
        $this->assertSame(5.0, (float) $batch->qty);
        $this->assertSame(8.0, (float) $batch->cost);
        $this->assertSame('2026-12-31', $batch->expiry_date?->toDateString());
        $this->assertSame('2026-10-20', $batch->production_date?->toDateString());
        $this->assertSame(5.0, $this->stockQty($warehouse->id, 1));
        $this->assertSame(PurchaseOrderStatus::RECEIVED, $order->fresh()->status);
    }

    public function test_receive_uses_store_warehouse_when_order_has_store_id(): void
    {
        $storeWarehouse = $this->makeWarehouse(['name' => '门店仓', 'store_id' => 7]);
        $supplier = $this->makeSupplier();

        $order = $this->purchaseService()->create([
            'supplier_id' => $supplier->id,
            'store_id' => 7,
            'items' => [
                ['item_id' => 1, 'item_type' => 'material', 'qty' => 2, 'unit_price' => 4.00],
            ],
        ]);

        $order = $this->purchaseService()->approve($order);
        $item = $order->items->first();

        $this->purchaseService()->receive($order, [
            ['purchase_order_item_id' => $item->id, 'qty' => 2],
        ]);

        $this->assertSame(2.0, $this->stockQty($storeWarehouse->id, 1));
        $this->assertSame($storeWarehouse->id, $order->fresh()->warehouse_id);
    }

    public function test_approve_twice_is_rejected(): void
    {
        $warehouse = $this->makeWarehouse();
        $order = $this->purchaseService()->approve($this->createOrder($warehouse));

        $this->expectException(InvalidStateTransitionException::class);

        $this->purchaseService()->approve($order);
    }
}
