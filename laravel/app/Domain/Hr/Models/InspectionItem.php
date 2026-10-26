<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 巡检检查项；result 为 null 表示未评分（提交前必须全部评分）。
 *
 * @property int $id
 * @property int $inspection_id
 * @property string $item
 * @property string|null $result
 * @property string|null $note
 * @property string|null $photo
 */
class InspectionItem extends Model
{
    protected $table = 'inspection_items';

    protected $fillable = [
        'inspection_id',
        'item',
        'result',
        'note',
        'photo',
    ];

    protected $casts = [
        'inspection_id' => 'integer',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id', 'id');
    }
}
