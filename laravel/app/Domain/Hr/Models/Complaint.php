<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 门店客诉。
 *
 * @property int $id
 * @property int $store_id
 * @property string|null $order_number
 * @property string $type
 * @property string $content
 * @property string $status
 * @property int|null $handler_id
 * @property string|null $resolution
 * @property Carbon|null $handled_at
 */
class Complaint extends Model
{
    protected $table = 'complaints';

    protected $fillable = [
        'store_id',
        'order_number',
        'type',
        'content',
        'status',
        'handler_id',
        'resolution',
        'handled_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'handler_id' => 'integer',
        'handled_at' => 'datetime',
    ];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'handler_id', 'id');
    }
}
