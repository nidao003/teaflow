<?php

namespace App\Domain\Integration\Models;

use App\Domain\Integration\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 开放 API 客户端（第三方应用）：secret 只存 sha256 摘要，明文仅在签发时返回一次。
 */
class ApiClient extends Model
{
    protected $table = 'api_clients';

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected $attributes = [
        'status' => ClientStatus::ACTIVE,
    ];

    public function tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class, 'api_client_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->status === ClientStatus::ACTIVE;
    }
}
