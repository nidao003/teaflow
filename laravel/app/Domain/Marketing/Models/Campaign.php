<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use App\Domain\Marketing\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 营销活动。
 *
 * rules_json 结构（按 type 区分，金额均为两位小数字符串）：
 * - 通用：min_amount（门槛金额）、max_per_member（每人次数，0/缺省为不限）、
 *         stackable（是否可与其他活动叠加，默认 false）、stack_group（叠加分组，同组互斥）；
 * - full_reduction：thresholds: [{min_amount, discount_amount}, ...]；
 * - percent：percent（实付百分比 1-100，90 表示 9 折）、max_discount（可选封顶）；
 * - second_half：按商品件数计算，每两件第二件半价；
 * - gift：gift_sku_id、gift_quantity（默认 1）、min_amount。
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $scope
 * @property int|null $store_id
 * @property array<string, mixed>|null $rules_json
 * @property Carbon|null $start_at
 * @property Carbon|null $end_at
 * @property string $status
 * @property int $priority
 */
class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $fillable = [
        'name',
        'type',
        'scope',
        'store_id',
        'rules_json',
        'start_at',
        'end_at',
        'status',
        'priority',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'rules_json' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function skus(): HasMany
    {
        return $this->hasMany(CampaignSku::class, 'campaign_id', 'id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CampaignUsage::class, 'campaign_id', 'id');
    }

    public function isActive(): bool
    {
        return (string) $this->status === CampaignStatus::ACTIVE;
    }
}
