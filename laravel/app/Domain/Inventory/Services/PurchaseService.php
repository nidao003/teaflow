<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\BizType;
use App\Domain\Inventory\Enums\ItemType;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Exceptions\InvalidStateTransitionException;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\PurchaseOrderItem;
use App\Domain\Inventory\Models\Receipt;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 采购服务：create → approve → receive → close / cancel。
 * 收货时调用 StockService::receive() 生成库存流水与库存余额（库存的唯一出口仍是 stock_ledger）。
 */
class PurchaseService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * 创建采购单（draft）。
     *
     * @param  array{
     *     supplier_id:int, store_id?:int|null, warehouse_id?:int|null,
     *     expected_at?:string|null, operator_id?:int|null, remark?:string|null,
     *     items: array<int, array{item_type?:string, item_id:int, item_name?:string, unit?:string,
     *         qty:float|int|string, unit_price?:float|int|string, batch_no?:string,
     *         production_date?:string, expiry_date?:string}>
     * }  $data
     */
    public function create(array $data): PurchaseOrder
    {
        $items = $data['items'] ?? [];

        if (! is_array($items) || $items === []) {
            throw new InventoryException('采购单至少需要一行明细');
        }

        $supplierId = (int) ($data['supplier_id'] ?? 0);
        if ($supplierId <= 0) {
            throw new InventoryException('缺少 supplier_id');
        }

        if (! Supplier::query()->whereKey($supplierId)->exists()) {
            throw new InventoryException('供应商不存在：'.$supplierId);
        }

        return DB::transaction(function () use ($data, $items, $supplierId): PurchaseOrder {
            $order = PurchaseOrder::create([
                'order_no' => $this->generateNo('PO', 'purchase_orders', 'order_no'),
                'store_id' => isset($data['store_id']) ? (int) $data['store_id'] : null,
                'warehouse_id' => isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                'supplier_id' => $supplierId,
                'status' => PurchaseOrderStatus::DRAFT,
                'expected_at' => $this->dateOrNull($data['expected_at'] ?? null),
                'operator_id' => isset($data['operator_id']) ? (int) $data['operator_id'] : null,
                'remark' => $data['remark'] ?? null,
                'total_qty' => 0,
                'total_amount' => 0,
            ]);

            $totalQty = 0.0;
            $totalAmount = 0.0;

            foreach ($items as $line) {
                $qty = $this->positiveQty($line['qty'] ?? null);
                $unitPrice = $this->money($line['unit_price'] ?? 0);

                if ($unitPrice < 0) {
                    throw new InventoryException('采购单价不能为负');
                }

                $amount = $this->money($qty * $unitPrice);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'item_type' => ItemType::fromValue($line['item_type'] ?? null),
                    'item_id' => (int) ($line['item_id'] ?? 0),
                    'item_name' => $line['item_name'] ?? null,
                    'unit' => $line['unit'] ?? 'unit',
                    'qty' => $qty,
                    'received_qty' => 0,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'batch_no' => $line['batch_no'] ?? null,
                    'production_date' => $this->dateOrNull($line['production_date'] ?? null),
                    'expiry_date' => $this->dateOrNull($line['expiry_date'] ?? null),
                ]);

                $totalQty += $qty;
                $totalAmount += $amount;
            }

            $order->forceFill([
                'total_qty' => round($totalQty, 3),
                'total_amount' => round($totalAmount, 2),
            ])->save();

            return $order->refresh()->load('items');
        });
    }

    /**
     * 审核采购单：draft → approved。
     */
    public function approve(PurchaseOrder $order, ?int $operatorId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $operatorId): PurchaseOrder {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, PurchaseOrderStatus::APPROVED);

            $order->forceFill([
                'status' => PurchaseOrderStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $operatorId ?? $order->operator_id,
            ])->save();

            return $order->refresh();
        });
    }

    /**
     * 收货：approved / partial_received → partial_received / received。
     * 收货数量会生成库存流水（biz_type = purchase）并更新库存余额。
     *
     * @param  array<int, array{purchase_order_item_id:int, qty:float|int|string,
     *     batch_no?:string, production_date?:string, expiry_date?:string}>  $lines
     */
    public function receive(PurchaseOrder $order, array $lines, ?int $operatorId = null, ?string $receivedAt = null): Receipt
    {
        if ($lines === []) {
            throw new InventoryException('收货明细不能为空');
        }

        return DB::transaction(function () use ($order, $lines, $operatorId, $receivedAt): Receipt {
            $order = $this->lockOrder($order);
            $from = $this->status($order);

            if (! in_array($from, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIAL_RECEIVED], true)) {
                throw InvalidStateTransitionException::for(
                    '采购单收货',
                    $from->value,
                    PurchaseOrderStatus::RECEIVED->value
                );
            }

            $warehouse = $this->resolveWarehouse($order);
            $receivedAtDate = $receivedAt !== null ? Carbon::parse($receivedAt) : now();

            $receipt = Receipt::create([
                'receipt_no' => $this->generateNo('RCV', 'receipts', 'receipt_no'),
                'purchase_order_id' => $order->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $order->supplier_id,
                'store_id' => $order->store_id,
                'operator_id' => $operatorId,
                'received_at' => $receivedAtDate,
                'received_qty' => 0,
                'total_amount' => 0,
            ]);

            $totalQty = 0.0;
            $totalAmount = 0.0;
            $seen = [];

            foreach ($lines as $line) {
                $itemId = (int) ($line['purchase_order_item_id'] ?? $line['id'] ?? 0);
                if ($itemId <= 0) {
                    throw new InventoryException('收货行缺少 purchase_order_item_id');
                }

                if (isset($seen[$itemId])) {
                    throw new InventoryException('同一明细重复收货：'.$itemId);
                }
                $seen[$itemId] = true;

                $qty = $this->positiveQty($line['qty'] ?? null);

                $item = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $order->id)
                    ->whereKey($itemId)
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw new InventoryException('采购明细不存在：'.$itemId);
                }

                $remaining = $item->remainingQty();
                if ($qty > $remaining + 0.0001) {
                    throw new InventoryException(sprintf(
                        '收货数量超过未收数量：明细 %d 未收 %s，本次 %s',
                        $itemId,
                        $remaining,
                        $qty
                    ));
                }

                $batchNo = $line['batch_no'] ?? $item->batch_no;
                $productionDate = $line['production_date'] ?? $item->production_date?->toDateString();
                $expiryDate = $line['expiry_date'] ?? $item->expiry_date?->toDateString();

                if (empty($batchNo) && (! empty($productionDate) || ! empty($expiryDate))) {
                    $batchNo = 'B'.$receivedAtDate->format('Ymd').'-'.$item->id;
                }

                $this->stock->receive([
                    'warehouse_id' => $warehouse->id,
                    'item_type' => $item->item_type,
                    'item_id' => $item->item_id,
                    'unit' => $item->unit,
                    'qty' => $qty,
                    'unit_cost' => (float) $item->unit_price,
                    'biz_type' => BizType::PURCHASE,
                    'biz_id' => $order->order_no,
                    'batch_no' => $batchNo,
                    'production_date' => $productionDate,
                    'expiry_date' => $expiryDate,
                    'supplier_id' => $order->supplier_id,
                    'purchase_order_id' => $order->id,
                    'operator_id' => $operatorId,
                ]);

                $item->forceFill([
                    'received_qty' => round((float) $item->received_qty + $qty, 3),
                    'batch_no' => $batchNo ?: $item->batch_no,
                ])->save();

                $totalQty += $qty;
                $totalAmount += $this->money($qty * (float) $item->unit_price);
            }

            $receipt->forceFill([
                'received_qty' => round($totalQty, 3),
                'total_amount' => round($totalAmount, 2),
            ])->save();

            $items = PurchaseOrderItem::query()->where('purchase_order_id', $order->id)->get();
            $fullyReceived = $items->every(fn (PurchaseOrderItem $item): bool => $item->isFullyReceived());

            $order->forceFill([
                'status' => $fullyReceived ? PurchaseOrderStatus::RECEIVED : PurchaseOrderStatus::PARTIAL_RECEIVED,
                'received_at' => $fullyReceived ? $receivedAtDate : $order->received_at,
                'warehouse_id' => $warehouse->id,
            ])->save();

            return $receipt->refresh();
        });
    }

    /**
     * 关闭采购单：received → closed。
     */
    public function close(PurchaseOrder $order, ?int $operatorId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, PurchaseOrderStatus::CLOSED);

            $order->forceFill([
                'status' => PurchaseOrderStatus::CLOSED,
                'closed_at' => now(),
            ])->save();

            return $order->refresh();
        });
    }

    /**
     * 取消采购单：draft / approved → cancelled（已收货的单不能取消）。
     */
    public function cancel(PurchaseOrder $order, ?int $operatorId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, PurchaseOrderStatus::CANCELLED);

            $order->forceFill([
                'status' => PurchaseOrderStatus::CANCELLED,
                'cancelled_at' => now(),
            ])->save();

            return $order->refresh();
        });
    }

    // ---------------------------------------------------------------------
    // 内部实现
    // ---------------------------------------------------------------------

    private function lockOrder(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    private function status(PurchaseOrder $order): PurchaseOrderStatus
    {
        $status = $order->status;

        return $status instanceof PurchaseOrderStatus ? $status : PurchaseOrderStatus::from((string) $status);
    }

    private function assertTransition(PurchaseOrder $order, PurchaseOrderStatus $to): void
    {
        $from = $this->status($order);

        if (! $from->canTransitTo($to)) {
            throw InvalidStateTransitionException::for('采购单', $from->value, $to->value);
        }
    }

    /**
     * 收货仓库：采购单指定 → 门店仓 → 中央仓（不存在则自动建默认中央仓）。
     */
    private function resolveWarehouse(PurchaseOrder $order): Warehouse
    {
        if ($order->warehouse_id !== null) {
            $warehouse = Warehouse::query()->whereKey($order->warehouse_id)->first();
            if ($warehouse !== null) {
                return $warehouse;
            }
        }

        if ($order->store_id !== null) {
            $warehouse = Warehouse::query()
                ->where('store_id', $order->store_id)
                ->where('status', 'active')
                ->first();

            if ($warehouse !== null) {
                return $warehouse;
            }
        }

        $warehouse = Warehouse::query()
            ->whereNull('store_id')
            ->where('type', 'central')
            ->where('status', 'active')
            ->first();

        return $warehouse ?? Warehouse::create([
            'name' => '默认中央仓',
            'code' => 'WH-CENTRAL',
            'type' => 'central',
            'status' => 'active',
        ]);
    }

    private function generateNo(string $prefix, string $table, string $column): string
    {
        do {
            $no = $prefix.now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table($table)->where($column, $no)->exists());

        return $no;
    }

    private function positiveQty(mixed $qty): float
    {
        $value = round((float) $qty, 3);

        if ($value <= 0) {
            throw new InventoryException('数量必须大于 0');
        }

        return $value;
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}
