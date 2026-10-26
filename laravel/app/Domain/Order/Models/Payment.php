<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\PaymentChannel;
use App\Domain\Order\Enums\PaymentStatus;
use Illuminate\Support\Carbon;

/**
 * 支付单。
 *
 * 金额只允许由 PaymentService 依据服务端订单写入，禁止客户端传入。
 *
 * @property int $id
 * @property string $order_number
 * @property int|null $store_id
 * @property PaymentChannel $channel
 * @property string $out_trade_no
 * @property string|null $transaction_id
 * @property string $amount
 * @property PaymentStatus $status
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $raw_notify
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    protected $table = 'payments';

    protected $casts = [
        'order_number' => 'string',
        'store_id' => 'integer',
        'channel' => PaymentChannel::class,
        'amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
        'raw_notify' => 'array',
    ];
}
