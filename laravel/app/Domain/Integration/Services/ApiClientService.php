<?php

namespace App\Domain\Integration\Services;

use App\Domain\Integration\Enums\ClientStatus;
use App\Domain\Integration\Models\ApiClient;
use App\Domain\Integration\Models\ApiToken;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * 开放 API 密钥服务：签发/轮换/校验。
 *
 * 安全约定：
 * - client secret 与 access token 都只存 sha256 摘要，明文仅在签发时返回一次，绝不落库；
 * - 校验用 hash_equals 做常量时间比较，避免计时侧信道；
 * - token 支持 abilities（["*"] 通配）与过期/吊销。
 */
class ApiClientService
{
    public const CLIENT_ID_PREFIX = 'tfc_';

    public const TOKEN_PREFIX = 'tfp_';

    public const SECRET_LENGTH = 48;

    public const TOKEN_LENGTH = 48;

    /**
     * 签发客户端 + 首个访问令牌；client_secret 与 token_plain 只在这里返回一次。
     *
     * @param  list<string>  $abilities
     * @return array{client: ApiClient, client_secret: string, token: ApiToken, token_plain: string}
     */
    public function issue(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): array
    {
        $plainSecret = $this->generateSecret();

        /** @var ApiClient $client */
        $client = ApiClient::query()->create([
            'name' => $name,
            'client_id' => self::CLIENT_ID_PREFIX.Str::random(24),
            'secret_hash' => $this->hashSecret($plainSecret),
            'status' => ClientStatus::ACTIVE,
        ]);

        $issued = $this->issueToken($client, $abilities, $expiresAt);

        return [
            'client' => $client,
            'client_secret' => $plainSecret,
            'token' => $issued['token'],
            'token_plain' => $issued['token_plain'],
        ];
    }

    /**
     * 给已有客户端追加访问令牌。
     *
     * @param  list<string>  $abilities
     * @return array{token: ApiToken, token_plain: string}
     */
    public function issueToken(ApiClient $client, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): array
    {
        $plainToken = self::TOKEN_PREFIX.Str::random(self::TOKEN_LENGTH);

        /** @var ApiToken $token */
        $token = ApiToken::query()->create([
            'api_client_id' => (int) $client->getKey(),
            'token_hash' => $this->hashToken($plainToken),
            'abilities' => array_values($abilities),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'token_plain' => $plainToken];
    }

    /**
     * 轮换客户端密钥：返回新的明文密钥（仅此一次），旧密钥立即失效。
     */
    public function rotate(ApiClient $client): string
    {
        $plainSecret = $this->generateSecret();

        $client->fill(['secret_hash' => $this->hashSecret($plainSecret)])->save();

        return $plainSecret;
    }

    /**
     * 校验 client_id + secret，通过则刷新 last_used_at 并返回客户端。
     */
    public function verify(string $clientId, string $secret): ?ApiClient
    {
        $client = ApiClient::query()->where('client_id', $clientId)->first();

        if (! $client instanceof ApiClient || ! $client->isActive()) {
            return null;
        }

        if (! hash_equals((string) $client->getRawOriginal('secret_hash'), $this->hashSecret($secret))) {
            return null;
        }

        $client->fill(['last_used_at' => now()])->save();

        return $client;
    }

    /**
     * 按明文 token 找到可用令牌（未吊销、未过期、客户端启用），并刷新客户端 last_used_at。
     */
    public function findUsableToken(string $plainToken): ?ApiToken
    {
        $token = ApiToken::query()->where('token_hash', $this->hashToken($plainToken))->first();

        if (! $token instanceof ApiToken || ! $token->isUsable()) {
            return null;
        }

        $client = $token->client;

        if (! $client instanceof ApiClient || ! $client->isActive()) {
            return null;
        }

        $client->fill(['last_used_at' => now()])->save();

        return $token;
    }

    /**
     * 吊销单个令牌。
     */
    public function revoke(ApiToken $token): ApiToken
    {
        $token->fill(['revoked_at' => now()])->save();

        return $token->refresh();
    }

    /**
     * 吊销客户端下所有未吊销令牌。
     */
    public function revokeAll(ApiClient $client): int
    {
        return ApiToken::query()
            ->where('api_client_id', (int) $client->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * 令牌是否具备某能力（同时要求令牌仍可用）。
     */
    public function allows(ApiToken $token, string $ability): bool
    {
        return $token->isUsable() && $token->hasAbility($ability);
    }

    public function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function generateSecret(): string
    {
        return Str::random(self::SECRET_LENGTH);
    }
}
