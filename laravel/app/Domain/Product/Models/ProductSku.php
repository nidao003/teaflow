<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商品 SKU。products 仍是商品主表，SKU 只追加规格/价格/成本，不改动 products 结构。
 */
class ProductSku extends Model
{
    public const STATUS_ACTIVE = 1;

    public const STATUS_DISABLED = -1;

    protected $table = 'product_skus';

    protected $fillable = [
        'product_id',
        'spec_key',
        'price',
        'market_price',
        'cost',
        'status',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'price' => 'decimal:2',
        'market_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'status' => 'integer',
    ];

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'product_sku_id');
    }

    public function costCards(): HasMany
    {
        return $this->hasMany(CostCard::class, 'product_sku_id');
    }
}
