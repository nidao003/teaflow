<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 门店目标（周期 + 指标唯一）。
 *
 * @property int $id
 * @property int $store_id
 * @property string $period 周期，如 2026-10 / 2026-W43
 * @property string $metric 指标，如 revenue / orders
 * @property string $target_value
 * @property string $actual_value
 */
class Target extends Model
{
    protected $table = 'targets';

    protected $fillable = [
        'store_id',
        'period',
        'metric',
        'target_value',
        'actual_value',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'target_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
    ];
}
