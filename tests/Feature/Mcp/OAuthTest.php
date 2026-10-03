<?php

namespace Tests\Feature\Mcp;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UsesOAuthKeys;
use Tests\TestCase;

/**
 * OAuth for MCP clients, as Claude or Cursor go through it: discover the
 * endpoints, register, send the person to authorize (who allows it in the
 * web app), exchange the code, then call the MCP server with the token.
 */
class OAuthTest extends TestCase
{
    use UsesOAuthKeys;

    private const ACCEPT = ['Accept' => 'application/json, text/event-stream'];

    private const REDIRECT = 'https://client.example/callback';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOAuthKeys();
        config(['app.frontend_url' => 'https://tasks.example']);
    }

    private function register(string $name = 'Claude', string $redirect = self::REDIRECT): string
    {
        return $this->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [$redirect]])
            ->assertCreated()
            ->json('client_id');
    }

    /**
     * @return array{query: array<string, string>, verifier: string}
     */
    private function authorizeParams(string $clientId, array $overrides = []): array
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'query' => [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => 'the-clients-state',
            'scope' => 'mcp:use',
            ...$overrides,
        ]];
    }

    /** The id the API hands to the web app's consent page. */
    private function startAuthorization(array $query): string
    {
        $location = $this->get('/oauth/authorize?'.http_build_query($query))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://tasks.example/authorize?request=', $location);

        return Str::after($location, 'request=');
    }

    /** Query parameters of the URL the browser is sent back to. */
    private function callbackParams(string $url): array
    {
        $this->assertStringStartsWith(self::REDIRECT.'?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        return $params;
    }

    /**
     * The whole flow for $user; returns the token response.
     *
     * @return array{access_token: string, refresh_token: string, client_id: string}
     */
    private function connect(User $user, string $name = 'Claude'): array
    {
        $clientId = $this->register($name);
        ['query' => $query, 'verifier' => $verifier] = $this->authorizeParams($clientId);
        $id = $this->startAuthorization($query);

        Sanctum::actingAs($user);
        $redirect = $this->postJson("/api/oauth/authorizations/{$id}/approve")->assertOk()->json('data.redirect_url');
        $this->forgetUser();

        $tokens = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier,
            'code' => $this->callbackParams($redirect)['code'],
        ])->assertOk()->json();

        return [...$tokens, 'client_id' => $clientId];
    }

    private function mcp(string $token, string $method = 'tools/list', array $params = []): TestResponse
    {
        $this->forgetUser();

        return $this->withToken($token)
            ->withHeaders(self::ACCEPT)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }

    /** Each request in a test shares the app, and the guards cache their user. */
    private function forgetUser(): void
    {
        app('auth')->forgetGuards();
        $this->flushHeaders();
    }

    public function test_an_unauthenticated_mcp_request_points_the_client_at_the_oauth_metadata(): void
    {
        $response = $this->withHeaders(self::ACCEPT)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();

        $this->assertStringContainsString(
            'resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"',
            $response->headers->get('WWW-Authenticate')
        );

        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('authorization_servers.0', url('/'));

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_browser_based_clients_may_call_the_endpoints_cross_origin(): void
    {
        $origin = ['Origin' => 'https://inspector.example'];

        $this->withHeaders($origin)->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $this->withHeaders([...$origin, ...self::ACCEPT])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Expose-Headers', 'WWW-Authenticate, Mcp-Session-Id');

        $this->withHeaders([...$origin, 'Access-Control-Request-Method' => 'POST'])
            ->options('/oauth/token')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_the_full_flow_gives_a_token_for_the_mcp_server_only(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $user->tenant_id, 'name' => 'Ours']);
        Project::factory()->create(['name' => 'Someone elses']);

        $clientId = $this->register('Claude');
        ['query' => $query, 'verifier' => $verifier] = $this->authorizeParams($clientId);
        $id = $this->startAuthorization($query);

        Sanctum::actingAs($user);
        $this->getJson("/api/oauth/authorizations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.client.id', $clientId)
            ->assertJsonPath('data.client.name', 'Claude')
            ->assertJsonPath('data.client.redirect_host', 'client.example')
            ->assertJsonPath('data.scopes.0.id', 'mcp:use');

        $redirect = $this->postJson("/api/oauth/authorizations/{$id}/approve")->assertOk()->json('data.redirect_url');
        $params = $this->callbackParams($redirect);
        $this->assertSame('the-clients-state', $params['state']);

        // Each request is answered once.
        $this->postJson("/api/oauth/authorizations/{$id}/approve")->assertNotFound();
        $this->forgetUser();

        $exchange = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier,
            'code' => $params['code'],
        ];
        $this->postJson('/oauth/token', [...$exchange, 'code_verifier' => Str::random(64)])->assertStatus(400);
        $this->postJson('/oauth/token', [...$exchange, 'client_id' => 'not-a-uuid'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_client');

        $tokens = $this->postJson('/oauth/token', $exchange)
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->json();
        $this->assertLessThanOrEqual(3600, $tokens['expires_in']);
        $this->assertNotEmpty($tokens['refresh_token']);

        // A code works once.
        $this->postJson('/oauth/token', $exchange)->assertStatus(400);

        $this->mcp($tokens['access_token'])->assertOk()->assertJsonPath('id', 1);
        $list = $this->mcp($tokens['access_token'], 'tools/call', ['name' => 'list_projects', 'arguments' => []])->assertOk();
        $this->assertStringContainsString('Ours', $list->json('result.content.0.text'));
        $this->assertStringNotContainsString('Someone elses', $list->json('result.content.0.text'));

        // Not a key to the REST API.
        $this->forgetUser();
        $this->withToken($tokens['access_token'])->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_refresh_replaces_the_tokens(): void
    {
        $tokens = $this->connect(User::factory()->create());

        $fresh = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->json();

        $this->mcp($fresh['access_token'])->assertOk();
        $this->mcp($tokens['access_token'])->assertUnauthorized();
    }

    public function test_denying_sends_the_browser_back_with_access_denied(): void
    {
        $clientId = $this->register();
        $id = $this->startAuthorization($this->authorizeParams($clientId)['query']);

        Sanctum::actingAs(User::factory()->create());
        $redirect = $this->postJson("/api/oauth/authorizations/{$id}/deny")->assertOk()->json('data.redirect_url');

        $params = $this->callbackParams($redirect);
        $this->assertSame('access_denied', $params['error']);
        $this->assertSame('the-clients-state', $params['state']);
        $this->assertArrayNotHasKey('code', $params);
    }

    public function test_a_bad_request_is_refused_without_a_redirect_to_an_unregistered_address(): void
    {
        $clientId = $this->register();

        foreach ([
            ['redirect_uri' => 'https://attacker.example/callback'],
            ['client_id' => (string) Str::uuid()],
            ['client_id' => 'not-a-uuid'],
        ] as $overrides) {
            $response = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, $overrides)['query']));
            $this->assertTrue($response->isClientError(), json_encode($overrides));
            $this->assertNull($response->headers->get('Location'));
        }

        // A public client must use PKCE.
        $query = $this->authorizeParams($clientId)['query'];
        unset($query['code_challenge'], $query['code_challenge_method']);
        $this->get('/oauth/authorize?'.http_build_query($query))->assertStatus(400);

        // Once the client and its redirect URI check out, errors go back to it.
        $response = $this->get('/oauth/authorize?'.http_build_query($this->authorizeParams($clientId, ['scope' => 'admin'])['query']));
        $this->assertSame('invalid_scope', $this->callbackParams($response->headers->get('Location'))['error']);
    }

    public function test_registering_clients_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->register("Client {$i}");
        }

        $this->postJson('/oauth/register', ['client_name' => 'One too many', 'redirect_uris' => [self::REDIRECT]])
            ->assertTooManyRequests();
    }

    public function test_an_unknown_or_expired_request_is_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/oauth/authorizations/not-a-request')->assertNotFound();
        $this->postJson('/api/oauth/authorizations/not-a-request/approve')->assertNotFound();
        $this->postJson('/api/oauth/authorizations/not-a-request/deny')->assertNotFound();
    }

    public function test_the_consent_endpoints_need_a_signed_in_person(): void
    {
        $id = $this->startAuthorization($this->authorizeParams($this->register())['query']);

        $this->getJson("/api/oauth/authorizations/{$id}")->assertUnauthorized();
        $this->postJson("/api/oauth/authorizations/{$id}/approve")->assertUnauthorized();
    }

    public function test_connected_apps_are_listed_and_can_be_disconnected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tokens = $this->connect($user, 'Claude');
        $this->connect($other, 'Cursor');

        Sanctum::actingAs($user);
        $this->getJson('/api/oauth/connections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tokens['client_id'])
            ->assertJsonPath('data.0.name', 'Claude')
            ->assertJsonPath('data.0.redirect_host', 'client.example');

        // Someone else's connection is not theirs to end.
        Sanctum::actingAs($other);
        $this->deleteJson("/api/oauth/connections/{$tokens['client_id']}")->assertNotFound();
        $this->deleteJson('/api/oauth/connections/not-a-uuid')->assertNotFound();
        $this->mcp($tokens['access_token'])->assertOk();

        Sanctum::actingAs($user);
        $this->deleteJson("/api/oauth/connections/{$tokens['client_id']}")->assertOk();
        $this->getJson('/api/oauth/connections')->assertOk()->assertJsonCount(0, 'data');

        $this->mcp($tokens['access_token'])->assertUnauthorized();
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $tokens['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(400);
    }

    public function test_changing_the_password_disconnects_every_app(): void
    {
        $user = User::factory()->create();
        $tokens = $this->connect($user);

        Sanctum::actingAs($user);
        $this->putJson('/api/user', [
            'current_password' => 'password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertOk();

        $this->mcp($tokens['access_token'])->assertUnauthorized();
    }

    public function test_sanctum_tokens_still_reach_the_mcp_server(): void
    {
        $token = User::factory()->create()->createToken('claude')->plainTextToken;

        $this->mcp($token)->assertOk();
        $this->mcp('not-a-token')->assertUnauthorized();
    }
}
