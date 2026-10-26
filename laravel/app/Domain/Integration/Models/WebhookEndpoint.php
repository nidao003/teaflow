<?php

namespace App\Domain\Integration\Models;

use App\Domain\Integration\Enums\EndpointStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Webhook 订阅端点。
 *
 * secret 以 encrypted cast 存储（DB 密文，解密后用于 HMAC-SHA256 签名）；
 * events 为订阅事件数组，dispatcher 只向订阅了该事件的端点投递。
 */
class WebhookEndpoint extends Model
{
    protected $table = 'webhook_endpoints';

    protected $casts = [
        'events' => 'array',
        'secret' => 'encrypted',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    protected $attributes = [
        'status' => EndpointStatus::ACTIVE,
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->status === EndpointStatus::ACTIVE;
    }

    /**
     * 是否订阅了某事件。
     */
    public function subscribesTo(string $event): bool
    {
        return in_array($event, (array) $this->events, true);
    }

    /**
     * 解密后的签名密钥（仅域内发送时使用，不对外输出）。
     */
    public function plainSecret(): string
    {
        return (string) $this->getAttribute('secret');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EndpointStatus::ACTIVE);
    }
}
