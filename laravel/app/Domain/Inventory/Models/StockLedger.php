<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\BizType;
use App\Domain\Inventory\Enums\ItemType;
use App\Domain\Inventory\Enums\LedgerDirection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 库存流水：只追加表（禁止 update / delete），库存变动的唯一出口。
 * 只记录 created_at，不记录 updated_at。
 */
class StockLedger extends Model
{
    protected $table = 'stock_ledger';

    public $timestamps = false;

    protected $casts = [
        'warehouse_id' => 'integer',
        'item_id' => 'integer',
        'item_type' => ItemType::class,
        'direction' => LedgerDirection::class,
        'biz_type' => BizType::class,
        'qty' => 'float',
        'unit_cost' => 'float',
        'amount' => 'float',
        'balance_after' => 'float',
        'operator_id' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('stock_ledger 为只追加表，禁止更新流水');
        });

        static::deleting(function (): void {
            throw new \LogicException('stock_ledger 为只追加表，禁止删除流水');
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === LedgerDirection::IN;
    }
}
