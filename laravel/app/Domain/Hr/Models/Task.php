<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 门店任务。
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $assignee_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_at
 * @property string $status
 * @property string $priority
 * @property Carbon|null $completed_at
 */
class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'store_id',
        'assignee_id',
        'title',
        'description',
        'due_at',
        'status',
        'priority',
        'completed_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'assignee_id' => 'integer',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assignee_id', 'id');
    }
}
