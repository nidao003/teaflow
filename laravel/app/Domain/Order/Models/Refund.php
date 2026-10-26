<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\RefundStatus;
use Illuminate\Support\Carbon;

/**
 * 退款单。
 *
 * @property int $id
 * @property string $order_number
 * @property int|null $payment_id
 * @property string $refund_no
 * @property string $amount
 * @property string|null $reason
 * @property RefundStatus $status
 * @property int|null $operator_id
 * @property int|null $approved_by
 * @property Carbon|null $refunded_at
 * @property string|null $channel_refund_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Refund extends Model
{
    protected $table = 'refunds';

    protected $casts = [
        'order_number' => 'string',
        'payment_id' => 'integer',
        'refund_no' => 'string',
        'amount' => 'decimal:2',
        'status' => RefundStatus::class,
        'operator_id' => 'integer',
        'approved_by' => 'integer',
        'refunded_at' => 'datetime',
    ];
}
