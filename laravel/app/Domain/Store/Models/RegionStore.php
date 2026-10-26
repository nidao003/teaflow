<?php

namespace App\Domain\Store\Models;

use App\Models\Store;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 区域-门店关联（落地表 region_store）。
 *
 * unique(store_id) 由迁移保证：一家门店只属于一个区域。
 */
class RegionStore extends Model
{
    public const TABLE = 'region_store';

    protected $table = self::TABLE;

    protected $casts = [
        'region_id' => 'integer',
        'store_id' => 'integer',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }
}
