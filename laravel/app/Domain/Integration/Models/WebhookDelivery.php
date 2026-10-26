<?php

namespace App\Domain\Integration\Models;

use App\Domain\Integration\Enums\DeliveryStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Webhook 投递记录：一次投递一行，重试复用同一行并累加 attempts。
 */
class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    protected $casts = [
        'endpoint_id' => 'integer',
        'payload' => 'array',
        'attempts' => 'integer',
        'response_code' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => DeliveryStatus::PENDING,
        'attempts' => 0,
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id', 'id');
    }

    public function isPending(): bool
    {
        return $this->status === DeliveryStatus::PENDING;
    }

    public function isSuccess(): bool
    {
        return $this->status === DeliveryStatus::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === DeliveryStatus::FAILED;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::PENDING);
    }

    /**
     * 已到重试时间（next_retry_at 为空视为立即可重试）。
     */
    public function scopeDueForRetry(Builder $query, ?DateTimeInterface $now = null): Builder
    {
        $moment = $now === null ? now() : $now;

        return $query->where('status', DeliveryStatus::PENDING)
            ->where(function (Builder $inner) use ($moment): void {
                $inner->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $moment);
            });
    }
}
