<?php

namespace App\Domain\Product\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 配方版本（BOM 头）。同一 SKU 的版本号递增，生效日期区间不可重叠。
 */
class Recipe extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'recipes';

    protected $fillable = [
        'product_sku_id',
        'version',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected $casts = [
        'product_sku_id' => 'integer',
        'version' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'recipe_id');
    }

    public function costCards(): HasMany
    {
        return $this->hasMany(CostCard::class, 'recipe_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 在指定日期生效：effective_from <= day 且（effective_to 为空 或 effective_to >= day）。
     */
    public function scopeEffectiveOn(Builder $query, DateTimeInterface|string $date): Builder
    {
        $day = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (string) $date;

        return $query->whereDate('effective_from', '<=', $day)
            ->where(function (Builder $sub) use ($day) {
                $sub->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day);
            });
    }
}
