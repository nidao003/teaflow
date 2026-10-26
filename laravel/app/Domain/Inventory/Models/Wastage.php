<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ItemType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 报损记录：报损出库经 StockService::waste() 同时写 stock_ledger 与 wastages。
 */
class Wastage extends Model
{
    protected $table = 'wastages';

    protected $casts = [
        'warehouse_id' => 'integer',
        'item_id' => 'integer',
        'item_type' => ItemType::class,
        'batch_id' => 'integer',
        'qty' => 'float',
        'unit_cost' => 'float',
        'amount' => 'float',
        'wasted_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }
}
