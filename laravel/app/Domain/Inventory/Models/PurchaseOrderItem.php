<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ItemType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $casts = [
        'purchase_order_id' => 'integer',
        'item_id' => 'integer',
        'item_type' => ItemType::class,
        'qty' => 'float',
        'received_qty' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
        'production_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function remainingQty(): float
    {
        return round((float) $this->qty - (float) $this->received_qty, 3);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->received_qty + 0.0001 >= (float) $this->qty;
    }
}
