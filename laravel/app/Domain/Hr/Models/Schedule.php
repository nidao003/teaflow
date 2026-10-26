<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 排班。
 *
 * unique(staff_id, date, shift_type)；同一员工同一天不同班次之间不允许时间重叠（由服务层校验）。
 *
 * @property int $id
 * @property int $store_id
 * @property int $staff_id
 * @property string $date Y-m-d
 * @property string $shift_type
 * @property string $start_time HH:MM
 * @property string $end_time HH:MM
 * @property string $status
 */
class Schedule extends Model
{
    protected $table = 'schedules';

    protected $fillable = [
        'store_id',
        'staff_id',
        'date',
        'shift_type',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'staff_id' => 'integer',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }
}
