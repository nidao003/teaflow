<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use Illuminate\Support\Carbon;

/**
 * 订单状态审计事件（只追加）。
 *
 * 表结构只有 created_at，没有 updated_at。
 *
 * @property int $id
 * @property string $order_number
 * @property string|null $from_status
 * @property string $to_status
 * @property string $event
 * @property int|null $operator_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 */
class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_events';

    protected $casts = [
        'order_number' => 'string',
        'from_status' => 'string',
        'to_status' => 'string',
        'event' => 'string',
        'operator_id' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('order_events 为只追加表，禁止更新事件');
        });

        static::deleting(function (): void {
            throw new \LogicException('order_events 为只追加表，禁止删除事件');
        });
    }
}
