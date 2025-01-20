<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\TenantService;

class TenantServiceTest extends TestCase
{
    use RefreshDatabase; // Resets the database after each test.

    protected TenantService $tenantService;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize the service
        $this->tenantService = app(TenantService::class);
    }

    public function testGetAllTenants()
    {
        // Arrange: Create a few tenants in the database
        Tenant::factory()->count(3)->create();

        // Act: Call the service method
        $tenants = $this->tenantService->getAllTenants();

        // Assert: Check that the service returns the correct count
        $this->assertCount(3, $tenants);
    }

    public function testFindTenantById_Successful()
    {
        // Arrange: Create a tenant
        $tenant = Tenant::factory()->create();

        // Act: Fetch the tenant using the service
        $result = $this->tenantService->findTenantById($tenant->id);

        // Assert: Check if the service returns the correct tenant
        $this->assertEquals($tenant->id, $result->id);
        $this->assertEquals($tenant->name, $result->name);
    }

    public function testFindTenantById_NotFound()
    {
        // Act & Assert: Try to fetch a non-existent tenant
        $nonExistentId = 999;
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->tenantService->findTenantById($nonExistentId);
    }

    public function testCreateTenant()
    {
        // Arrange: Define the tenant data, including the slug
        $data = [
            'name' => 'New Tenant',
            'slug' => 'new-tenant', // Add slug field
            'settings' => '{}',     // Optional field, already handled as nullable
        ];

        // Act: Create the tenant using the service
        $tenant = $this->tenantService->createTenant($data);

        // Assert: Check that the tenant exists in the database
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'New Tenant',
            'slug' => 'new-tenant',
        ]);
    }

    public function testUpdateTenant()
    {
        // Arrange: Create a tenant and prepare updated data
        $tenant = Tenant::factory()->create();
        $data = ['name' => 'Updated Tenant'];

        // Act: Update the tenant using the service
        $updatedTenant = $this->tenantService->updateTenant($tenant, $data);

        // Assert: Check if the tenant was updated
        $this->assertEquals('Updated Tenant', $updatedTenant->name);
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Tenant',
        ]);
    }

    public function testDeleteTenant()
    {
        // Arrange: Create a tenant
        $tenant = Tenant::factory()->create();

        // Act: Delete the tenant using the service
        $this->tenantService->deleteTenant($tenant);

        // Assert: Ensure the tenant no longer exists in the database
        $this->assertDatabaseMissing('tenants', [
            'id' => $tenant->id,
        ]);
    }
}
