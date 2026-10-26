<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\StocktakeStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 盘点单：counting → counted → completed / cancelled。
 */
class Stocktake extends Model
{
    protected $table = 'stocktakes';

    protected $casts = [
        'warehouse_id' => 'integer',
        'status' => StocktakeStatus::class,
        'total_items' => 'integer',
        'diff_items' => 'integer',
        'diff_amount' => 'float',
        'counted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StocktakeItem::class, 'stocktake_id');
    }
}
