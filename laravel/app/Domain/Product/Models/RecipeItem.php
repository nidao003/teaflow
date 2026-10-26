<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 配方明细（BOM 行）：原料 + 标准用量 + 损耗率。
 */
class RecipeItem extends Model
{
    protected $table = 'recipe_items';

    protected $fillable = [
        'recipe_id',
        'material_id',
        'qty',
        'unit',
        'loss_rate',
    ];

    protected $casts = [
        'recipe_id' => 'integer',
        'material_id' => 'integer',
        'qty' => 'decimal:3',
        'loss_rate' => 'decimal:4',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * 含损耗的实际耗用量：qty × (1 + loss_rate)。
     */
    public function effectiveQty(): float
    {
        return (float) $this->qty * (1 + (float) $this->loss_rate);
    }
}
