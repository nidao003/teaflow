<?php

namespace App\Domain\Menu\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 售罄/恢复操作日志：只追加，记录门店、SKU、操作与备注。
 */
class SoldOutLog extends Model
{
    public const ACTION_SOLD_OUT = 'sold_out';

    public const ACTION_RESTORED = 'restored';

    public const ACTIONS = [self::ACTION_SOLD_OUT, self::ACTION_RESTORED];

    /**
     * 日志只追加，不需要 updated_at。
     */
    public const UPDATED_AT = null;

    protected $table = 'sold_out_logs';

    protected $fillable = [
        'store_id',
        'product_sku_id',
        'action',
        'operator_id',
        'note',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'product_sku_id' => 'integer',
        'operator_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
