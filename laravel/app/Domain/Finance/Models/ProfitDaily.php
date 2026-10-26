<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use Illuminate\Support\Carbon;

/**
 * 毛利日汇总：gross_profit = revenue - cost；gross_margin 为百分比（两位小数）。
 *
 * @property int $id
 * @property int $store_id
 * @property string $date Y-m-d
 * @property float $revenue
 * @property float $cost
 * @property float $gross_profit
 * @property float $gross_margin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProfitDaily extends Model
{
    protected $table = 'profit_daily';

    protected $casts = [
        'store_id' => 'integer',
        'revenue' => 'float',
        'cost' => 'float',
        'gross_profit' => 'float',
        'gross_margin' => 'float',
    ];
}
