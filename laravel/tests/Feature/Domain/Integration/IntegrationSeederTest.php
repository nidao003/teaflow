<?php

namespace Tests\Feature\Domain\Integration;

use App\Domain\Integration\Enums\EndpointStatus;
use App\Domain\Integration\Models\ApiClient;
use App\Domain\Integration\Models\WebhookEndpoint;
use App\Domain\Integration\Support\WebhookEvents;
use Database\Seeders\IntegrationSeeder;

class IntegrationSeederTest extends IntegrationTestCase
{
    public function test_seed_creates_disabled_demo_endpoint(): void
    {
        $this->seed(IntegrationSeeder::class);

        $endpoint = WebhookEndpoint::query()
            ->where('name', IntegrationSeeder::DEMO_ENDPOINT_NAME)
            ->firstOrFail();

        $this->assertSame(EndpointStatus::DISABLED, $endpoint->status);
        $this->assertSame(
            [WebhookEvents::ORDER_CREATED, WebhookEvents::ORDER_PAID],
            $endpoint->events
        );

        // seeder 不签发任何 API 客户端/密钥
        $this->assertSame(0, ApiClient::query()->count());
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed(IntegrationSeeder::class);
        $this->seed(IntegrationSeeder::class);

        $this->assertSame(1, WebhookEndpoint::query()->count());
    }
}
