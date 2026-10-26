<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 活动使用记录；同一活动+会员+订单号唯一（幂等）。
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $member_id
 * @property string|null $order_number
 * @property string $amount
 * @property Carbon|null $used_at
 */
class CampaignUsage extends Model
{
    protected $table = 'campaign_usages';

    protected $fillable = [
        'campaign_id',
        'member_id',
        'order_number',
        'amount',
        'used_at',
    ];

    protected $casts = [
        'campaign_id' => 'integer',
        'member_id' => 'integer',
        'amount' => 'decimal:2',
        'used_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id');
    }
}
