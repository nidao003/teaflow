<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use Illuminate\Support\Carbon;

/**
 * 渠道支付回调审计记录。
 *
 * notify_id 唯一；processed_at 为空表示未处理完（如验签失败、金额不符被拒绝）。
 *
 * @property int $id
 * @property string $channel
 * @property string $notify_id
 * @property array<string, mixed>|null $payload
 * @property bool $verified
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentCallback extends Model
{
    protected $table = 'payment_callbacks';

    protected $casts = [
        'channel' => 'string',
        'notify_id' => 'string',
        'payload' => 'array',
        'verified' => 'boolean',
        'processed_at' => 'datetime',
    ];
}
