<?php

namespace Tests\Feature\Domain\Integration;

use App\Domain\Integration\Models\ApiClient;
use App\Domain\Integration\Models\ApiToken;
use App\Domain\Integration\Models\WebhookEndpoint;
use App\Domain\Integration\Services\ApiClientService;
use App\Domain\Integration\Services\WebhookEndpointService;
use App\Domain\Integration\Support\WebhookEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 集成/开放域测试基类：RefreshDatabase + phpunit.xml 固定的 sqlite :memory:，webhook 用 Http::fake。
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array{name?: string, url?: string, events?: list<string>, secret?: string}  $overrides
     */
    protected function createEndpoint(array $overrides = []): WebhookEndpoint
    {
        /** @var WebhookEndpointService $endpoints */
        $endpoints = $this->app->make(WebhookEndpointService::class);

        return $endpoints->register(
            $overrides['name'] ?? '测试端点',
            $overrides['url'] ?? 'https://example.test/hooks/teaflow',
            $overrides['events'] ?? [WebhookEvents::ORDER_CREATED],
            $overrides['secret'] ?? null,
        );
    }

    /**
     * @param  list<string>  $abilities
     * @return array{client: ApiClient, client_secret: string, token: ApiToken, token_plain: string}
     */
    protected function issueClient(string $name = '测试客户端', array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): array
    {
        /** @var ApiClientService $clients */
        $clients = $this->app->make(ApiClientService::class);

        return $clients->issue($name, $abilities, $expiresAt);
    }

    protected function requestWithBearer(string $plainToken): Request
    {
        return Request::create(
            '/api/open/orders',
            'GET',
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$plainToken],
        );
    }

    protected function requestWithApiKey(string $plainToken): Request
    {
        return Request::create(
            '/api/open/orders',
            'GET',
            [],
            [],
            [],
            ['HTTP_X_API_KEY' => $plainToken],
        );
    }
}
