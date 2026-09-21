<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class TokenApiTest extends TestCase
{
    use ActsAsTenantUser;

    public function test_creates_a_token_that_authenticates_requests(): void
    {
        $user = $this->actingAsTenantUser();

        $response = $this->postJson('/api/tokens', ['name' => 'claude-desktop'])->assertCreated();

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'claude-desktop']);

        // Only the token, not the Sanctum::actingAs user, must authenticate the next request.
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_lists_tokens_without_exposing_secrets(): void
    {
        $user = $this->actingAsTenantUser();
        $user->createToken('one');
        $user->createToken('two');

        $this->getJson('/api/tokens')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'last_used_at', 'created_at']]])
            ->assertJsonMissingPath('data.0.token');
    }

    public function test_revokes_a_token(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('script')->plainTextToken;
        $id = $user->tokens()->first()->id;

        $this->withToken($plain)->deleteJson("/api/tokens/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);

        app('auth')->forgetGuards();
        $this->withToken($plain)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_cannot_revoke_another_users_token(): void
    {
        $victim = User::factory()->create();
        $victim->createToken('keep');
        $id = $victim->tokens()->first()->id;

        $this->actingAsTenantUser();

        $this->deleteJson("/api/tokens/{$id}")->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $id]);
    }
}
