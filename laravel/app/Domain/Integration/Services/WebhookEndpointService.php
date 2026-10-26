<?php

namespace App\Domain\Integration\Services;

use App\Domain\Integration\Enums\EndpointStatus;
use App\Domain\Integration\Exceptions\IntegrationException;
use App\Domain\Integration\Models\WebhookEndpoint;
use App\Domain\Integration\Support\WebhookEvents;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Webhook 端点服务：注册/更新/启停，事件校验，按事件找订阅端点。
 *
 * secret 生成后以 encrypted cast 落库（DB 密文），明文仅通过模型访问器在域内使用。
 */
class WebhookEndpointService
{
    public const SECRET_LENGTH = 40;

    /**
     * @param  list<string>  $events
     */
    public function register(string $name, string $url, array $events, ?string $secret = null): WebhookEndpoint
    {
        $this->assertUrl($url);
        $this->assertEvents($events);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::query()->create([
            'name' => $name,
            'url' => $url,
            'secret' => $secret ?? $this->generateSecret(),
            'events' => $this->normalizeEvents($events),
            'status' => EndpointStatus::ACTIVE,
        ]);

        return $endpoint;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WebhookEndpoint $endpoint, array $attributes): WebhookEndpoint
    {
        $allowed = ['name', 'url', 'events', 'status', 'secret'];
        /** @var array<string, mixed> $fillable */
        $fillable = array_intersect_key($attributes, array_flip($allowed));

        if (array_key_exists('url', $fillable)) {
            $this->assertUrl((string) $fillable['url']);
        }

        if (array_key_exists('events', $fillable)) {
            /** @var list<string> $events */
            $events = is_array($fillable['events']) ? array_values($fillable['events']) : [];
            $this->assertEvents($events);
            $fillable['events'] = $this->normalizeEvents($events);
        }

        if (array_key_exists('status', $fillable) && ! in_array($fillable['status'], EndpointStatus::all(), true)) {
            throw new IntegrationException('非法的端点状态：'.(string) $fillable['status']);
        }

        $endpoint->fill($fillable)->save();

        return $endpoint->refresh();
    }

    public function enable(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $endpoint->fill(['status' => EndpointStatus::ACTIVE])->save();

        return $endpoint->refresh();
    }

    public function disable(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $endpoint->fill(['status' => EndpointStatus::DISABLED])->save();

        return $endpoint->refresh();
    }

    /**
     * 订阅了某事件的启用端点（事件在 PHP 侧过滤，兼容 sqlite/mysql 两种 JSON 语法）。
     *
     * @return Collection<int, WebhookEndpoint>
     */
    public function activeEndpointsForEvent(string $event): Collection
    {
        /** @var Collection<int, WebhookEndpoint> $endpoints */
        $endpoints = WebhookEndpoint::query()
            ->where('status', EndpointStatus::ACTIVE)
            ->orderBy('id')
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($event))
            ->values();

        return $endpoints;
    }

    public function generateSecret(): string
    {
        return Str::random(self::SECRET_LENGTH);
    }

    private function assertUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true)) {
            throw IntegrationException::invalidUrl($url);
        }
    }

    /**
     * @param  array<int, mixed>  $events
     */
    private function assertEvents(array $events): void
    {
        if ($events === []) {
            throw new IntegrationException('Webhook 至少需要订阅一个事件');
        }

        foreach ($events as $event) {
            if (! is_string($event) || ! WebhookEvents::isKnown($event)) {
                throw IntegrationException::unknownEvent(is_string($event) ? $event : (string) gettype($event));
            }
        }
    }

    /**
     * @param  list<string>  $events
     * @return list<string>
     */
    private function normalizeEvents(array $events): array
    {
        return array_values(array_unique($events));
    }
}
