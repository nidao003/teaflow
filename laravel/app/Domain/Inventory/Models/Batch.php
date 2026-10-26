<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 批次：按到期日 FIFO 出库（先到期先出）。
 */
class Batch extends Model
{
    protected $table = 'batches';

    protected $casts = [
        'warehouse_id' => 'integer',
        'item_id' => 'integer',
        'item_type' => ItemType::class,
        'production_date' => 'date',
        'expiry_date' => 'date',
        'qty' => 'float',
        'cost' => 'float',
        'supplier_id' => 'integer',
        'purchase_order_id' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * 仍有剩余数量的批次。
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('qty', '>', 0);
    }

    /**
     * FIFO 排序：先到期先出（无到期日的排最后）。
     */
    public function scopeFifo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('production_date')
            ->orderBy('id');
    }

    public function isExpired(?string $date = null): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        $date ??= now()->toDateString();

        return $this->expiry_date->toDateString() < $date;
    }
}
