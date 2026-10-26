<?php

namespace App\Domain\Integration\Services;

use App\Domain\Integration\Models\ApiClient;
use App\Domain\Integration\Models\ApiToken;
use Illuminate\Http\Request;

/**
 * 开放 API 鉴权服务（只做校验逻辑，不注册路由/中间件；由调用方在 Controller 或路由层使用）。
 *
 * 支持两种凭据：
 * - Authorization: Bearer <token>
 * - X-Api-Key: <token>
 *
 * 另提供 client_id + client_secret 的客户端校验（用于换取 token 的 client_credentials 流程）。
 */
class OpenApiAuth
{
    public const API_KEY_HEADER = 'X-Api-Key';

    private ApiClientService $clients;

    public function __construct(?ApiClientService $clients = null)
    {
        $this->clients = $clients ?? new ApiClientService;
    }

    /**
     * 从请求解析并校验访问令牌；失败返回 null，不抛异常。
     */
    public function authenticate(Request $request): ?ApiToken
    {
        $plainToken = $this->plainToken($request);

        if ($plainToken === null) {
            return null;
        }

        return $this->clients->findUsableToken($plainToken);
    }

    /**
     * 鉴权 + 能力校验。
     */
    public function authorize(Request $request, string $ability): bool
    {
        $token = $this->authenticate($request);

        return $token instanceof ApiToken && $this->clients->allows($token, $ability);
    }

    /**
     * client_id + client_secret 校验（client_credentials 换 token 前使用）。
     */
    public function verifyClient(string $clientId, string $clientSecret): ?ApiClient
    {
        return $this->clients->verify($clientId, $clientSecret);
    }

    /**
     * 当前请求的明文令牌（Bearer 优先，其次 X-Api-Key）。
     */
    public function plainToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $header = $request->header(self::API_KEY_HEADER);
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return null;
    }
}
