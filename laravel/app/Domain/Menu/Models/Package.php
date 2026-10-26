<?php

namespace App\Domain\Menu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 套餐：固定套餐价 + 成员 SKU 组成，可用性依赖成员商品在门店的可售状态。
 */
class Package extends Model
{
    public const STATUS_ON = 'on';

    public const STATUS_OFF = 'off';

    public const STATUSES = [self::STATUS_ON, self::STATUS_OFF];

    protected $table = 'packages';

    protected $fillable = [
        'name',
        'price',
        'status',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class, 'package_id');
    }
}
