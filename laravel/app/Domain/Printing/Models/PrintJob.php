<?php

namespace App\Domain\Printing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 打印任务：payload 保存渲染后的内容与变量，状态 pending → printing → success/failed。
 */
class PrintJob extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PRINTING = 'printing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PRINTING,
        self::STATUS_SUCCESS,
        self::STATUS_FAILED,
    ];

    protected $table = 'print_jobs';

    protected $fillable = [
        'store_id',
        'order_number',
        'template_id',
        'device_id',
        'type',
        'payload',
        'status',
        'retries',
        'error',
        'printed_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'template_id' => 'integer',
        'payload' => 'array',
        'retries' => 'integer',
        'printed_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'template_id');
    }

    /**
     * 渲染后的待打印文本。
     */
    public function content(): string
    {
        return (string) ($this->payload['content'] ?? '');
    }

    /**
     * 是否已打印成功；补打请使用 PrintService::reprint() 生成新任务。
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
