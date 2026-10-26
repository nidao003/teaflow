<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use Illuminate\Support\Carbon;

/**
 * 营业收入日汇总（派生表，可由 RevenueService/DailyCloseService 重算）。
 *
 * @property int $id
 * @property int $store_id
 * @property string $date Y-m-d
 * @property int $order_count
 * @property float $gross_amount
 * @property float $discount_amount
 * @property float $refund_amount
 * @property float $net_amount
 * @property float $payment_fee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RevenueDaily extends Model
{
    protected $table = 'revenue_daily';

    protected $casts = [
        'store_id' => 'integer',
        'order_count' => 'integer',
        'gross_amount' => 'float',
        'discount_amount' => 'float',
        'refund_amount' => 'float',
        'net_amount' => 'float',
        'payment_fee' => 'float',
    ];
}
