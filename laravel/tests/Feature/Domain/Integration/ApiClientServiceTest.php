<?php

namespace Tests\Feature\Domain\Integration;

use App\Domain\Integration\Enums\ClientStatus;
use App\Domain\Integration\Models\ApiClient;
use App\Domain\Integration\Services\ApiClientService;
use Illuminate\Support\Facades\DB;

class ApiClientServiceTest extends IntegrationTestCase
{
    private ApiClientService $clients;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clients = $this->app->make(ApiClientService::class);
    }

    public function test_issue_returns_plain_secret_once_and_stores_only_hash(): void
    {
        $issued = $this->issueClient('订单开放应用', ['order.view']);

        $client = $issued['client'];
        $plainSecret = $issued['client_secret'];
        $plainToken = $issued['token_plain'];

        $this->assertStringStartsWith(ApiClientService::CLIENT_ID_PREFIX, $client->client_id);
        $this->assertStringStartsWith(ApiClientService::TOKEN_PREFIX, $plainToken);
        $this->assertSame(['order.view'], $issued['token']->abilities);

        // 客户端 secret：只落 sha256 摘要
        $storedSecretHash = (string) DB::table('api_clients')->where('id', $client->getKey())->value('secret_hash');
        $this->assertSame(hash('sha256', $plainSecret), $storedSecretHash);
        $this->assertNotSame($plainSecret, $storedSecretHash);

        // 访问令牌：只落 sha256 摘要
        $storedTokenHash = (string) DB::table('api_tokens')->where('id', $issued['token']->getKey())->value('token_hash');
        $this->assertSame(hash('sha256', $plainToken), $storedTokenHash);
        $this->assertNotSame($plainToken, $storedTokenHash);
    }

    public function test_verify_client_secret_accepts_correct_and_rejects_wrong(): void
    {
        $issued = $this->issueClient();
        $client = $issued['client'];
        $secret = $issued['client_secret'];

        $this->assertNull($this->clients->verify((string) $client->client_id, 'wrong-secret'));

        $verified = $this->clients->verify((string) $client->client_id, $secret);
        $this->assertInstanceOf(ApiClient::class, $verified);
        $this->assertNotNull($verified->fresh()->last_used_at);

        // 停用的客户端即使密钥正确也拒绝
        $verified->fill(['status' => ClientStatus::DISABLED])->save();
        $this->assertNull($this->clients->verify((string) $client->client_id, $secret));
    }

    public function test_rotate_invalidates_old_secret(): void
    {
        $issued = $this->issueClient();
        $client = $issued['client'];
        $oldSecret = $issued['client_secret'];

        $newSecret = $this->clients->rotate($client);

        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertNull($this->clients->verify((string) $client->client_id, $oldSecret));
        $this->assertNotNull($this->clients->verify((string) $client->client_id, $newSecret));
    }

    public function test_find_usable_token_and_ability_check(): void
    {
        $issued = $this->issueClient('订单应用', ['order.view']);

        $token = $this->clients->findUsableToken($issued['token_plain']);
        $this->assertNotNull($token);
        $this->assertTrue($this->clients->allows($token, 'order.view'));
        $this->assertFalse($this->clients->allows($token, 'order.refund'));

        $this->assertNull($this->clients->findUsableToken('tfp_not-a-real-token'));
    }

    public function test_wildcard_ability_allows_everything(): void
    {
        $issued = $this->issueClient('平台应用', ['*']);

        $token = $this->clients->findUsableToken($issued['token_plain']);
        $this->assertNotNull($token);
        $this->assertTrue($this->clients->allows($token, 'order.refund'));
        $this->assertTrue($this->clients->allows($token, 'report.view'));
    }

    public function test_revoked_token_is_rejected(): void
    {
        $issued = $this->issueClient();
        $token = $issued['token'];

        $this->clients->revoke($token);

        $this->assertNull($this->clients->findUsableToken($issued['token_plain']));
        $this->assertFalse($this->clients->allows($token->refresh(), 'order.view'));
    }

    public function test_expired_token_is_rejected(): void
    {
        $issued = $this->issueClient('过期应用', ['order.view'], now()->subMinute());

        $this->assertNull($this->clients->findUsableToken($issued['token_plain']));

        $future = $this->issueClient('未过期应用', ['order.view'], now()->addDay());
        $this->assertNotNull($this->clients->findUsableToken($future['token_plain']));
    }

    public function test_disabled_client_token_is_rejected(): void
    {
        $issued = $this->issueClient();
        $issued['client']->fill(['status' => ClientStatus::DISABLED])->save();

        $this->assertNull($this->clients->findUsableToken($issued['token_plain']));
    }

    public function test_revoke_all_tokens_of_client(): void
    {
        $issued = $this->issueClient();
        $second = $this->clients->issueToken($issued['client']);

        $this->assertSame(2, $this->clients->revokeAll($issued['client']));

        $this->assertNull($this->clients->findUsableToken($issued['token_plain']));
        $this->assertNull($this->clients->findUsableToken($second['token_plain']));
    }
}
