<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Tenant;

class TenantLanguageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_validates_required_fields_when_creating_a_tenant()
    {
        $response = $this->postJson(route('tenant.store'), []); // Sending empty data

        $response->assertStatus(422) // HTTP 422: Unprocessable Entity
        ->assertJsonValidationErrors([
            'name' => __('tenant.store.validation.requiredFields'),
            'email' => __('tenant.store.validation.requiredFields'), // Or as per fields required
        ]);
    }

    /** @test */
    public function it_validates_unique_tenant_name_on_creation()
    {
        $existingTenant = Tenant::factory()->create(['name' => 'Test Tenant']);

        $response = $this->postJson(route('tenant.store'), [
            'name' => $existingTenant->name,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => __('tenant.store.validation.uniqueError'),
            ]);
    }

    /** @test */
    public function it_creates_a_tenant_successfully()
    {
        $data = [
            'name' => 'New Tenant',
            'email' => 'tenant@example.com',
        ];

        $response = $this->postJson(route('tenant.store'), $data);

        $response->assertStatus(201) // HTTP 201: Created
        ->assertJsonFragment([
            'message' => __('tenant.store.success'),
        ]);

        $this->assertDatabaseHas('tenants', $data); // Verify the data in the database
    }

    /** @test */
    public function it_validates_required_fields_when_updating_a_tenant()
    {
        $tenant = Tenant::factory()->create();

        $response = $this->patchJson(route('tenant.update', $tenant->id), []); // Sending empty data

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => __('tenant.update.validation.requiredFields'),
            ]);
    }

    /** @test */
    public function it_validates_unique_tenant_name_on_update()
    {
        $existingTenant = Tenant::factory()->create(['name' => 'Existing Tenant']);
        $updatingTenant = Tenant::factory()->create();

        $response = $this->patchJson(route('tenant.update', $updatingTenant->id), [
            'name' => $existingTenant->name,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => __('tenant.update.validation.uniqueError'),
            ]);
    }

    /** @test */
    public function it_updates_a_tenant_successfully()
    {
        $tenant = Tenant::factory()->create();

        $updateData = [
            'name' => 'Updated Tenant Name',
        ];

        $response = $this->patchJson(route('tenant.update', $tenant->id), $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => __('tenant.update.success'),
            ]);

        $this->assertDatabaseHas('tenants', $updateData);
    }

    /** @test */
    public function it_returns_not_found_error_when_updating_a_nonexistent_tenant()
    {
        $response = $this->patchJson(route('tenant.update', 9999), [
            'name' => 'Nonexistent Tenant',
        ]);

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => __('tenant.notFound.single'),
            ]);
    }

    /** @test */
    public function it_deletes_a_tenant_successfully()
    {
        $tenant = Tenant::factory()->create();

        $response = $this->deleteJson(route('tenant.destroy', $tenant->id));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => __('tenant.destroy.success'),
            ]);

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    /** @test */
    public function it_returns_not_found_error_when_deleting_a_nonexistent_tenant()
    {
        $response = $this->deleteJson(route('tenant.destroy', 9999));

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => __('tenant.notFound.single'),
            ]);
    }
}
