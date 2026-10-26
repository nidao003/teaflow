<?php

namespace App\Domain\Menu\Models;

use App\Domain\Product\Models\ProductSku;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 门店菜单项：门店级的价格覆盖、上下架、售罄与时段可售配置。
 */
class StoreMenuItem extends Model
{
    public const STATUS_ON = 'on';

    public const STATUS_OFF = 'off';

    public const STATUSES = [self::STATUS_ON, self::STATUS_OFF];

    protected $table = 'store_menu_items';

    protected $fillable = [
        'store_id',
        'product_sku_id',
        'price',
        'status',
        'sold_out',
        'sold_out_at',
        'available_from',
        'available_to',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'product_sku_id' => 'integer',
        'price' => 'decimal:2',
        'sold_out' => 'boolean',
        'sold_out_at' => 'datetime',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }
}
