<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $table = 'receipts';

    protected $casts = [
        'purchase_order_id' => 'integer',
        'warehouse_id' => 'integer',
        'supplier_id' => 'integer',
        'store_id' => 'integer',
        'received_qty' => 'float',
        'total_amount' => 'float',
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
