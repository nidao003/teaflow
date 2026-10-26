<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 活动适用商品；没有记录表示「全场通用」。
 *
 * @property int $id
 * @property int $campaign_id
 * @property int|null $product_sku_id
 */
class CampaignSku extends Model
{
    protected $table = 'campaign_skus';

    protected $fillable = [
        'campaign_id',
        'product_sku_id',
    ];

    protected $casts = [
        'campaign_id' => 'integer',
        'product_sku_id' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id');
    }
}
