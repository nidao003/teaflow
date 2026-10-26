<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 采购单：draft → approved → partial_received / received → closed / cancelled。
 */
class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $casts = [
        'store_id' => 'integer',
        'warehouse_id' => 'integer',
        'supplier_id' => 'integer',
        'status' => PurchaseOrderStatus::class,
        'total_qty' => 'float',
        'total_amount' => 'float',
        'expected_at' => 'date',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'purchase_order_id');
    }

    public function isFullyReceived(): bool
    {
        return $this->items()->get()->every(
            fn (PurchaseOrderItem $item): bool => (float) $item->received_qty + 0.0001 >= (float) $item->qty
        );
    }

    public function remainingQty(): float
    {
        return round($this->items()->get()->sum(
            fn (PurchaseOrderItem $item): float => max(0.0, (float) $item->qty - (float) $item->received_qty)
        ), 3);
    }
}
