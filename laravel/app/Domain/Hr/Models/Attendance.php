<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 考勤记录：一个员工一天一条（unique(staff_id, date)）。
 *
 * @property int $id
 * @property int $staff_id
 * @property string $date Y-m-d
 * @property string|null $clock_in HH:MM
 * @property string|null $clock_out HH:MM
 * @property string $status
 * @property string $source
 */
class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'staff_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'source',
    ];

    protected $casts = [
        'staff_id' => 'integer',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }
}
