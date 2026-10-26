<?php

namespace App\Domain\Integration\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 开放 API 访问令牌：token 只存 sha256 摘要；abilities 为权限点数组，["*"] 表示全部。
 */
class ApiToken extends Model
{
    protected $table = 'api_tokens';

    protected $casts = [
        'api_client_id' => 'integer',
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id', 'id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(?DateTimeInterface $now = null): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        $moment = $now === null ? now() : $now;

        return $this->expires_at->lessThanOrEqualTo($moment);
    }

    /**
     * 是否可继续使用：未吊销、未过期。
     */
    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * 是否具备某个权限点（支持 ["*"] 通配）。
     */
    public function hasAbility(string $ability): bool
    {
        $abilities = (array) $this->abilities;

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
