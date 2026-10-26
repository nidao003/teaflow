<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use App\Domain\Fulfillment\Enums\VerifyCodeStatus;
use App\Domain\Fulfillment\Enums\VerifyCodeType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 核销码：一次性使用，绑定订单与门店，过期/已用/作废均不可再核销。
 *
 * @property int $id
 * @property string $code
 * @property string $order_number
 * @property int $store_id
 * @property VerifyCodeType $type
 * @property VerifyCodeStatus $status
 * @property Carbon|null $used_at
 * @property int|null $used_by
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VerifyCode extends Model
{
    protected $table = 'verify_codes';

    protected $fillable = [
        'code',
        'order_number',
        'store_id',
        'type',
        'status',
        'used_at',
        'used_by',
        'expires_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'used_by' => 'integer',
        'type' => VerifyCodeType::class,
        'status' => VerifyCodeStatus::class,
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(?CarbonInterface $now = null): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->lessThanOrEqualTo($now ?? Carbon::now());
    }

    public function isUsable(): bool
    {
        return $this->status === VerifyCodeStatus::ISSUED && ! $this->isExpired();
    }
}
