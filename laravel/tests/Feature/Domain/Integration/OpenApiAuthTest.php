<?php

namespace Tests\Feature\Domain\Integration;

use App\Domain\Integration\Services\OpenApiAuth;
use Illuminate\Http\Request;

class OpenApiAuthTest extends IntegrationTestCase
{
    private OpenApiAuth $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->app->make(OpenApiAuth::class);
    }

    public function test_authenticate_with_bearer_token(): void
    {
        $issued = $this->issueClient('订单应用', ['order.view']);

        $token = $this->auth->authenticate($this->requestWithBearer($issued['token_plain']));

        $this->assertNotNull($token);
        $this->assertSame((int) $issued['token']->getKey(), (int) $token->getKey());
    }

    public function test_authenticate_with_api_key_header(): void
    {
        $issued = $this->issueClient('订单应用', ['order.view']);

        $token = $this->auth->authenticate($this->requestWithApiKey($issued['token_plain']));

        $this->assertNotNull($token);
        $this->assertSame((int) $issued['token']->getKey(), (int) $token->getKey());
    }

    public function test_authenticate_rejects_missing_or_invalid_credentials(): void
    {
        $this->assertNull($this->auth->authenticate(Request::create('/api/open/orders')));
        $this->assertNull($this->auth->authenticate($this->requestWithBearer('tfp_invalid-token')));
    }

    public function test_authorize_checks_ability(): void
    {
        $issued = $this->issueClient('订单应用', ['order.view']);

        $this->assertTrue($this->auth->authorize($this->requestWithBearer($issued['token_plain']), 'order.view'));
        $this->assertFalse($this->auth->authorize($this->requestWithBearer($issued['token_plain']), 'order.refund'));
        $this->assertFalse($this->auth->authorize(Request::create('/api/open/orders'), 'order.view'));
    }

    public function test_verify_client_credentials(): void
    {
        $issued = $this->issueClient();
        $clientId = (string) $issued['client']->client_id;

        $this->assertNull($this->auth->verifyClient($clientId, 'wrong-secret'));
        $this->assertNotNull($this->auth->verifyClient($clientId, $issued['client_secret']));
    }

    public function test_bearer_token_takes_precedence_over_api_key_header(): void
    {
        $bearer = $this->issueClient('Bearer 应用', ['order.view']);
        $apiKey = $this->issueClient('API Key 应用', ['order.view']);

        $request = Request::create('/api/open/orders', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$bearer['token_plain'],
            'HTTP_X_API_KEY' => $apiKey['token_plain'],
        ]);

        $token = $this->auth->authenticate($request);

        $this->assertNotNull($token);
        $this->assertSame((int) $bearer['token']->getKey(), (int) $token->getKey());
    }
}
