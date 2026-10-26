<?php

namespace Tests\Feature\Domain\Integration;

use App\Domain\Integration\Enums\EndpointStatus;
use App\Domain\Integration\Exceptions\IntegrationException;
use App\Domain\Integration\Services\WebhookEndpointService;
use App\Domain\Integration\Support\WebhookEvents;
use Illuminate\Support\Facades\DB;

class WebhookEndpointServiceTest extends IntegrationTestCase
{
    private WebhookEndpointService $endpoints;

    protected function setUp(): void
    {
        parent::setUp();
        $this->endpoints = $this->app->make(WebhookEndpointService::class);
    }

    public function test_register_generates_secret_and_encrypts_it_at_rest(): void
    {
        $endpoint = $this->endpoints->register(
            '订单回调',
            'https://example.test/hooks/teaflow',
            [WebhookEvents::ORDER_CREATED],
        );

        $this->assertSame(EndpointStatus::ACTIVE, $endpoint->status);
        $this->assertTrue($endpoint->isActive());
        $this->assertTrue($endpoint->subscribesTo(WebhookEvents::ORDER_CREATED));
        $this->assertFalse($endpoint->subscribesTo(WebhookEvents::ORDER_PAID));

        $plain = $endpoint->plainSecret();
        $this->assertSame(WebhookEndpointService::SECRET_LENGTH, strlen($plain));

        // DB 中必须是密文，不能出现明文 secret
        $raw = (string) DB::table('webhook_endpoints')->where('id', $endpoint->getKey())->value('secret');
        $this->assertNotSame($plain, $raw);
        $this->assertStringNotContainsString($plain, $raw);
        $this->assertSame($plain, $endpoint->fresh()->plainSecret());
    }

    public function test_register_accepts_explicit_secret(): void
    {
        $endpoint = $this->endpoints->register(
            '自定义密钥端点',
            'https://example.test/hooks/custom',
            [WebhookEvents::ORDER_PAID],
            'my-custom-secret',
        );

        $this->assertSame('my-custom-secret', $endpoint->plainSecret());
    }

    public function test_register_rejects_unknown_event(): void
    {
        $this->expectException(IntegrationException::class);

        $this->endpoints->register('坏事件', 'https://example.test/hooks/x', ['order.unknown']);
    }

    public function test_register_rejects_empty_events(): void
    {
        $this->expectException(IntegrationException::class);

        $this->endpoints->register('空事件', 'https://example.test/hooks/x', []);
    }

    public function test_register_rejects_non_http_url(): void
    {
        $this->expectException(IntegrationException::class);

        $this->endpoints->register('坏地址', 'ftp://example.test/hooks/x', [WebhookEvents::ORDER_CREATED]);
    }

    public function test_update_deduplicates_events_and_checks_status(): void
    {
        $endpoint = $this->createEndpoint(['events' => [WebhookEvents::ORDER_CREATED]]);

        $updated = $this->endpoints->update($endpoint, [
            'name' => '新名字',
            'url' => 'https://example.test/hooks/v2',
            'events' => [WebhookEvents::ORDER_PAID, WebhookEvents::ORDER_PAID],
        ]);

        $this->assertSame('新名字', $updated->name);
        $this->assertSame('https://example.test/hooks/v2', $updated->url);
        $this->assertSame([WebhookEvents::ORDER_PAID], $updated->events);
    }

    public function test_update_rejects_unknown_status(): void
    {
        $endpoint = $this->createEndpoint();

        $this->expectException(IntegrationException::class);

        $this->endpoints->update($endpoint, ['status' => 'paused']);
    }

    public function test_enable_disable_toggles_status(): void
    {
        $endpoint = $this->createEndpoint();

        $disabled = $this->endpoints->disable($endpoint);
        $this->assertSame(EndpointStatus::DISABLED, $disabled->status);
        $this->assertFalse($disabled->isActive());

        $enabled = $this->endpoints->enable($disabled);
        $this->assertSame(EndpointStatus::ACTIVE, $enabled->status);
        $this->assertTrue($enabled->isActive());
    }

    public function test_active_endpoints_for_event_filters_by_event_and_status(): void
    {
        $subscribed = $this->createEndpoint(['name' => 'A', 'events' => [WebhookEvents::ORDER_CREATED]]);
        $this->createEndpoint(['name' => 'B', 'events' => [WebhookEvents::ORDER_PAID]]);
        $disabled = $this->createEndpoint(['name' => 'C', 'events' => [WebhookEvents::ORDER_CREATED]]);
        $this->endpoints->disable($disabled);

        $ids = $this->endpoints->activeEndpointsForEvent(WebhookEvents::ORDER_CREATED)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([(int) $subscribed->getKey()], $ids);
    }
}
