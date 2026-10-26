<?php

namespace Database\Seeders;

use App\Domain\Integration\Models\WebhookEndpoint;
use App\Domain\Integration\Services\WebhookEndpointService;
use App\Domain\Integration\Support\WebhookEvents;
use Illuminate\Database\Seeder;

/**
 * 集成/开放域基础数据 seeding（幂等）。
 *
 * 只造一个「默认停用」的示例 Webhook 端点，方便对接方在后台看到字段结构；
 * 不 seed 任何 API 客户端/密钥（密钥必须由 ApiClientService 签发，seeder 不能落明文）。
 */
class IntegrationSeeder extends Seeder
{
    public const DEMO_ENDPOINT_NAME = '示例 Webhook（默认停用）';

    public const DEMO_ENDPOINT_URL = 'https://example.test/hooks/teaflow';

    public function run(WebhookEndpointService $endpoints): void
    {
        if (WebhookEndpoint::query()->where('name', self::DEMO_ENDPOINT_NAME)->exists()) {
            return;
        }

        $endpoint = $endpoints->register(
            self::DEMO_ENDPOINT_NAME,
            self::DEMO_ENDPOINT_URL,
            [WebhookEvents::ORDER_CREATED, WebhookEvents::ORDER_PAID],
        );

        $endpoints->disable($endpoint);
    }
}
