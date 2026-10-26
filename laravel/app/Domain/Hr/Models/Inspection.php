<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 巡检单：提交时按检查项结果计算得分。
 *
 * @property int $id
 * @property int $store_id
 * @property string $template_name
 * @property int|null $inspector_id
 * @property string $score
 * @property string $status
 * @property Carbon|null $checked_at
 */
class Inspection extends Model
{
    protected $table = 'inspections';

    protected $fillable = [
        'store_id',
        'template_name',
        'inspector_id',
        'score',
        'status',
        'checked_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'inspector_id' => 'integer',
        'score' => 'decimal:2',
        'checked_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class, 'inspection_id', 'id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'inspector_id', 'id');
    }
}
