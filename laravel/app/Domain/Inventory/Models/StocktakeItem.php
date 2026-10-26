<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ItemType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StocktakeItem extends Model
{
    protected $table = 'stocktake_items';

    protected $casts = [
        'stocktake_id' => 'integer',
        'item_id' => 'integer',
        'item_type' => ItemType::class,
        'book_qty' => 'float',
        'counted_qty' => 'float',
        'diff_qty' => 'float',
        'unit_cost' => 'float',
        'diff_amount' => 'float',
        'adjusted' => 'boolean',
    ];

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class, 'stocktake_id');
    }
}
