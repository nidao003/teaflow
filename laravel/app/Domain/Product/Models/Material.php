<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 原料主数据。平均单价不落在本表，由库存域 stock_items.avg_cost 或成本卡快照提供。
 */
class Material extends Model
{
    protected $table = 'materials';

    protected $fillable = [
        'name',
        'unit',
        'category',
        'shelf_life_days',
        'safety_stock',
    ];

    protected $casts = [
        'shelf_life_days' => 'integer',
        'safety_stock' => 'decimal:3',
    ];

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'material_id');
    }
}
