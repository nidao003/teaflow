<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use Illuminate\Support\Carbon;

/**
 * 营业成本日汇总（原料/人工/其他）。
 *
 * @property int $id
 * @property int $store_id
 * @property string $date Y-m-d
 * @property float $material_cost
 * @property float $labor_cost
 * @property float $other_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CostDaily extends Model
{
    protected $table = 'cost_daily';

    protected $casts = [
        'store_id' => 'integer',
        'material_cost' => 'float',
        'labor_cost' => 'float',
        'other_cost' => 'float',
    ];

    public function total(): float
    {
        return round($this->material_cost + $this->labor_cost + $this->other_cost, 2);
    }
}
