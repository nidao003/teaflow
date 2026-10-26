<?php

namespace App\Domain\Menu\Models;

use App\Domain\Product\Models\ProductSku;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 套餐成员：一个套餐包含某 SKU 的数量。
 */
class PackageItem extends Model
{
    protected $table = 'package_items';

    protected $fillable = [
        'package_id',
        'product_sku_id',
        'qty',
    ];

    protected $casts = [
        'package_id' => 'integer',
        'product_sku_id' => 'integer',
        'qty' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }
}
