<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 成本卡：一次成本计算的不可变快照（snapshot_json 记录配方版本、明细单价与来源）。
 */
class CostCard extends Model
{
    protected $table = 'cost_cards';

    protected $fillable = [
        'product_sku_id',
        'version',
        'recipe_id',
        'unit_cost',
        'calc_at',
        'snapshot_json',
    ];

    protected $casts = [
        'product_sku_id' => 'integer',
        'version' => 'integer',
        'recipe_id' => 'integer',
        'unit_cost' => 'decimal:2',
        'calc_at' => 'datetime',
        'snapshot_json' => 'array',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }
}
