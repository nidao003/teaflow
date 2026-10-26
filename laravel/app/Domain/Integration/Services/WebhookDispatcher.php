<?php

namespace App\Domain\Integration\Services;

use App\Domain\Integration\Enums\DeliveryStatus;
use App\Domain\Integration\Exceptions\IntegrationException;
use App\Domain\Integration\Models\WebhookDelivery;
use App\Domain\Integration\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Webhook 投递服务。
 *
 * 发送实现用 Laravel Http（而非 spatie WebhookCall）：需要同步拿到响应码、自己控制
 * 重试节奏并落 webhook_deliveries 审计记录，spatie 的队列投递/重试语义无法直接表达「同一条
 * 投递记录累加 attempts + next_retry_at」。签名与 spatie DefaultSigner 一致：
 * HMAC-SHA256(原始请求体, endpoint secret)。
 *
 * 重试策略：MAX_ATTEMPTS 次封顶，第 n 次失败后 next_retry_at = now + BASE_RETRY_SECONDS * n；
 * 达到上限后 status=failed 且不再排重试。
 */
class WebhookDispatcher
{
    public const MAX_ATTEMPTS = 3;

    public const BASE_RETRY_SECONDS = 60;

    public const TIMEOUT_SECONDS = 10;

    public const EVENT_HEADER = 'X-TeaFlow-Event';

    public const SIGNATURE_HEADER = 'X-TeaFlow-Signature';

    public const DELIVERY_HEADER = 'X-TeaFlow-Delivery';

    public const MAX_ERROR_LENGTH = 1000;

    private WebhookEndpointService $endpoints;

    public function __construct(?WebhookEndpointService $endpoints = null)
    {
        $this->endpoints = $endpoints ?? new WebhookEndpointService;
    }

    /**
     * 按事件向所有订阅的启用端点投递，返回本次产生的投递记录。
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<int, WebhookDelivery>
     */
    public function dispatch(string $event, array $payload = []): Collection
    {
        /** @var Collection<int, WebhookDelivery> $deliveries */
        $deliveries = collect();

        foreach ($this->endpoints->activeEndpointsForEvent($event) as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'endpoint_id' => (int) $endpoint->getKey(),
                'event' => $event,
                'payload' => $payload,
                'status' => DeliveryStatus::PENDING,
                'attempts' => 0,
            ]);

            $deliveries->push($this->send($delivery, $endpoint));
        }

        return $deliveries;
    }

    /**
     * 发送（或重发）一条投递记录。
     */
    public function send(WebhookDelivery $delivery, ?WebhookEndpoint $endpoint = null): WebhookDelivery
    {
        if (! $endpoint instanceof WebhookEndpoint) {
            $endpoint = $delivery->endpoint;
        }

        if (! $endpoint instanceof WebhookEndpoint) {
            throw IntegrationException::missingEndpoint((int) $delivery->getKey());
        }

        $body = $this->encodePayload((array) $delivery->payload);
        $attempts = (int) $delivery->attempts + 1;
        $responseCode = null;
        $error = null;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    self::EVENT_HEADER => (string) $delivery->event,
                    self::DELIVERY_HEADER => (string) $delivery->getKey(),
                    self::SIGNATURE_HEADER => $this->signature($body, $endpoint->plainSecret()),
                ])
                ->withBody($body, 'application/json')
                ->post((string) $endpoint->url);

            $responseCode = $response->status();

            if (! $response->successful()) {
                $error = 'HTTP '.$responseCode.': '.mb_substr($response->body(), 0, 300);
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        if ($error === null) {
            $delivery->fill([
                'status' => DeliveryStatus::SUCCESS,
                'attempts' => $attempts,
                'response_code' => $responseCode,
                'error' => null,
                'next_retry_at' => null,
            ])->save();

            $endpoint->fill(['last_success_at' => now()])->save();

            return $delivery->refresh();
        }

        $willRetry = $attempts < self::MAX_ATTEMPTS;

        $delivery->fill([
            'status' => $willRetry ? DeliveryStatus::PENDING : DeliveryStatus::FAILED,
            'attempts' => $attempts,
            'response_code' => $responseCode,
            'error' => mb_substr($error, 0, self::MAX_ERROR_LENGTH),
            'next_retry_at' => $willRetry ? now()->addSeconds(self::BASE_RETRY_SECONDS * $attempts) : null,
        ])->save();

        $endpoint->fill(['last_failure_at' => now()])->save();

        return $delivery->refresh();
    }

    /**
     * 重试已到期（next_retry_at <= now）的 pending 投递。
     *
     * @return Collection<int, WebhookDelivery>
     */
    public function retryPending(int $limit = 100): Collection
    {
        /** @var Collection<int, WebhookDelivery> $results */
        $results = collect();

        $due = WebhookDelivery::query()
            ->dueForRetry()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($due as $delivery) {
            $results->push($this->send($delivery));
        }

        return $results;
    }

    /**
     * 签名：HMAC-SHA256(原始请求体, secret)，十六进制小写。
     */
    public function signature(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }
}
