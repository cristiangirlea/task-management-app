<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use ActsAsTenantUser;

    public function test_shows_the_current_workspace(): void
    {
        $user = $this->actingAsTenantUser();
        User::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->getJson('/api/tenant')
            ->assertOk()
            ->assertJsonPath('message', __('tenant.show.success'))
            ->assertJsonPath('data.id', $user->tenant_id)
            ->assertJsonPath('data.users_count', 2);
    }

    public function test_updates_the_current_workspace(): void
    {
        $user = $this->actingAsTenantUser();

        $this->putJson('/api/tenant', ['name' => 'Renamed', 'slug' => 'renamed', 'settings' => ['theme' => 'dark']])
            ->assertOk()
            ->assertJsonPath('message', __('tenant.update.success'))
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.settings.theme', 'dark');

        $this->assertDatabaseHas('tenants', ['id' => $user->tenant_id, 'slug' => 'renamed']);
    }

    public function test_slug_must_be_unique_across_tenants(): void
    {
        Tenant::factory()->create(['slug' => 'taken']);
        $this->actingAsTenantUser();

        $this->putJson('/api/tenant', ['slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_keeping_the_current_slug_is_allowed(): void
    {
        $user = $this->actingAsTenantUser();

        $this->putJson('/api/tenant', ['slug' => $user->tenant->slug, 'name' => 'Same slug'])->assertOk();
    }

    public function test_messages_follow_the_application_locale(): void
    {
        $this->actingAsTenantUser();
        app()->setLocale('fr');

        $this->getJson('/api/tenant')
            ->assertOk()
            ->assertJsonPath('message', 'Locataire récupéré avec succès.');

        $this->putJson('/api/tenant', ['name' => 'Nouveau nom'])
            ->assertOk()
            ->assertJsonPath('message', 'Le locataire a été mis à jour avec succès.');
    }
}
