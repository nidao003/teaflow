<?php

namespace Tests\Feature\Domain\Integration;

use App\Domain\Integration\Enums\DeliveryStatus;
use App\Domain\Integration\Models\WebhookDelivery;
use App\Domain\Integration\Services\WebhookDispatcher;
use App\Domain\Integration\Services\WebhookEndpointService;
use App\Domain\Integration\Support\WebhookEvents;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

class WebhookDispatcherTest extends IntegrationTestCase
{
    private WebhookDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->app->make(WebhookDispatcher::class);
    }

    public function test_dispatch_signs_body_with_endpoint_secret(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $endpoint = $this->createEndpoint(['secret' => 'unit-test-secret']);
        $payload = ['order_id' => 123, 'amount' => '18.00', 'note' => '测试订单'];

        $deliveries = $this->dispatcher->dispatch(WebhookEvents::ORDER_CREATED, $payload);

        $this->assertCount(1, $deliveries);
        $delivery = $deliveries->first();

        Http::assertSent(function (ClientRequest $request) use ($endpoint, $delivery, $payload): bool {
            $body = $request->body();
            $expectedSignature = hash_hmac('sha256', $body, $endpoint->plainSecret());

            return $request->url() === $endpoint->url
                && $request->method() === 'POST'
                && $request->hasHeader(WebhookDispatcher::SIGNATURE_HEADER, $expectedSignature)
                && $request->hasHeader(WebhookDispatcher::EVENT_HEADER, WebhookEvents::ORDER_CREATED)
                && $request->hasHeader(WebhookDispatcher::DELIVERY_HEADER, (string) $delivery->getKey())
                && $body === $this->dispatcher->encodePayload($payload);
        });
    }

    public function test_only_subscribed_endpoints_receive_delivery(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $subscribed = $this->createEndpoint(['name' => 'A', 'events' => [WebhookEvents::ORDER_CREATED]]);
        $other = $this->createEndpoint(['name' => 'B', 'events' => [WebhookEvents::ORDER_PAID]]);

        $deliveries = $this->dispatcher->dispatch(WebhookEvents::ORDER_CREATED, ['order_id' => 1]);

        $this->assertCount(1, $deliveries);
        $this->assertSame((int) $subscribed->getKey(), (int) $deliveries->first()->endpoint_id);
        $this->assertSame(0, WebhookDelivery::query()->where('endpoint_id', $other->getKey())->count());
        Http::assertSentCount(1);
    }

    public function test_disabled_endpoint_is_skipped(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $endpoint = $this->createEndpoint(['events' => [WebhookEvents::ORDER_CREATED]]);
        $this->app->make(WebhookEndpointService::class)->disable($endpoint);

        $deliveries = $this->dispatcher->dispatch(WebhookEvents::ORDER_CREATED, ['order_id' => 1]);

        $this->assertCount(0, $deliveries);
        $this->assertSame(0, WebhookDelivery::query()->count());
        Http::assertNothingSent();
    }

    public function test_success_marks_delivery_and_endpoint(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $endpoint = $this->createEndpoint();

        $delivery = $this->dispatcher->dispatch(WebhookEvents::ORDER_CREATED, ['order_id' => 1])->first();

        $this->assertSame(DeliveryStatus::SUCCESS, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->response_code);
        $this->assertNull($delivery->error);
        $this->assertNull($delivery->next_retry_at);
        $this->assertTrue($delivery->isSuccess());

        $fresh = $endpoint->fresh();
        $this->assertNotNull($fresh->last_success_at);
        $this->assertNull($fresh->last_failure_at);
    }

    public function test_failure_schedules_retry_then_gives_up_after_max_attempts(): void
    {
        Http::fake(['*' => Http::response('server error', 500)]);
        $endpoint = $this->createEndpoint();

        $delivery = $this->dispatcher->dispatch(WebhookEvents::ORDER_CREATED, ['order_id' => 1])->first()->refresh();

        $this->assertSame(DeliveryStatus::PENDING, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(500, $delivery->response_code);
        $this->assertStringContainsString('HTTP 500', (string) $delivery->error);
        $this->assertNotNull($delivery->next_retry_at);
        $this->assertNotNull($endpoint->fresh()->last_failure_at);

        // 未到重试时间：不重发
        $this->assertCount(0, $this->dispatcher->retryPending());
        Http::assertSentCount(1);

        // 第 2 次：到期后重试，仍失败
        $this->travel(61)->seconds();
        $this->assertCount(1, $this->dispatcher->retryPending());
        $delivery->refresh();
        $this->assertSame(2, $delivery->attempts);
        $this->assertSame(DeliveryStatus::PENDING, $delivery->status);

        // 第 3 次：达到上限，终态 failed 且不再排重试
        $this->travel(121)->seconds();
        $this->assertCount(1, $this->dispatcher->retryPending());
        $delivery->refresh();
        $this->assertSame(3, $delivery->attempts);
        $this->assertSame(DeliveryStatus::FAILED, $delivery->status);
        $this->assertNull($delivery->next_retry_at);
        $this->assertTrue($delivery->isFailed());

        Http::assertSentCount(3);
        $this->assertSame(1, WebhookDelivery::query()->count());
    }

    public function test_retry_can_succeed_after_failure(): void
    {
        Http::fakeSequence()
            ->push('', 500)
            ->push('', 200);

        $endpoint = $this->createEndpoint();
        $delivery = $this->dispatcher->dispatch(WebhookEvents::ORDER_CREATED, ['order_id' => 1])->first();
        $this->assertSame(DeliveryStatus::PENDING, $delivery->status);

        $this->travel(61)->seconds();
        $this->dispatcher->retryPending();

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::SUCCESS, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertNull($delivery->next_retry_at);
        $this->assertNotNull($endpoint->fresh()->last_success_at);
        Http::assertSentCount(2);
    }
}
