<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use ActsAsTenantUser;

    public function test_register_creates_user_workspace_and_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'workspace_name' => 'Analytical Engines',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonPath('data.user.tenant.name', 'Analytical Engines')
            ->assertJsonPath('data.user.tenant.slug', 'analytical-engines')
            ->assertJsonStructure(['data' => ['token']]);

        $tenant = Tenant::where('slug', 'analytical-engines')->firstOrFail();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'tenant_id' => $tenant->id]);
    }

    public function test_register_defaults_workspace_name_and_keeps_slugs_unique(): void
    {
        Tenant::factory()->create(['slug' => 'adas-workspace']);

        $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated()
            ->assertJsonPath('data.user.tenant.name', "Ada's Workspace")
            ->assertJsonPath('data.user.tenant.slug', 'adas-workspace-2');
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/register', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_returns_a_token(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password']);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);

        $token = $response->json('data.token');
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->getJson('/api/tenant')->assertUnauthorized();
    }

    public function test_get_user_includes_tenant(): void
    {
        $user = $this->actingAsTenantUser();

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.tenant.id', $user->tenant_id);
    }

    public function test_update_user(): void
    {
        $user = $this->actingAsTenantUser();

        $this->putJson('/api/user', ['name' => 'Renamed', 'email' => 'renamed@example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'renamed@example.com']);
    }

    public function test_update_user_rejects_taken_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAsTenantUser();

        $this->putJson('/api/user', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        app('auth')->forgetGuards(); // drop the per-request cached user
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_delete_user(): void
    {
        $user = $this->actingAsTenantUser();

        $this->deleteJson('/api/user')->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
