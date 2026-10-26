<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\ReconciliationStatus;
use Illuminate\Support\Carbon;

/**
 * 渠道对账日汇总：diff = channel_amount - system_amount。
 *
 * @property int $id
 * @property int $store_id
 * @property string $date Y-m-d
 * @property string $channel
 * @property float $system_amount
 * @property float $channel_amount
 * @property float $diff
 * @property ReconciliationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReconciliationDaily extends Model
{
    protected $table = 'reconciliation_daily';

    protected $casts = [
        'store_id' => 'integer',
        'system_amount' => 'float',
        'channel_amount' => 'float',
        'diff' => 'float',
        'status' => ReconciliationStatus::class,
    ];
}
