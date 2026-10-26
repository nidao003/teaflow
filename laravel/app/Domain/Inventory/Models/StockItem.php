<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ItemType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 仓库 + 物料维度的库存余额。
 * qty 只能通过 StockService 变动（流水是唯一出口）；version 为乐观锁版本号。
 */
class StockItem extends Model
{
    protected $table = 'stock_items';

    protected $casts = [
        'warehouse_id' => 'integer',
        'item_id' => 'integer',
        'item_type' => ItemType::class,
        'qty' => 'float',
        'locked_qty' => 'float',
        'avg_cost' => 'float',
        'version' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'item_id', 'item_id')
            ->where('item_type', $this->item_type?->value ?? $this->getRawOriginal('item_type'));
    }

    /**
     * 可用库存 = 库存数量 - 锁定数量。
     */
    public function availableQty(): float
    {
        return round((float) $this->qty - (float) $this->locked_qty, 3);
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->qty <= 0;
    }
}
