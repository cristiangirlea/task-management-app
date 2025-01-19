<?php

namespace Tests\Unit\Repositories;

use App\Models\Tenant;
use App\Repositories\TenantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected TenantRepository $tenantRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantRepository = new TenantRepository();
    }

    /**
     * Test if we can get all tenants.
     */
    public function testCanGetAllTenants()
    {
        // Arrange: create tenants
        Tenant::factory()->count(3)->create();

        // Act: retrieve all tenants
        $tenants = $this->tenantRepository->getAll();

        // Assert: check if the number of tenants matches
        $this->assertCount(3, $tenants);
    }

    /**
     * Test if we can find a tenant by its ID.
     */
    public function testCanFindTenantById()
    {
        // Arrange: create a tenant
        $tenant = Tenant::factory()->create();

        // Act: find tenant by ID
        $foundTenant = $this->tenantRepository->findById($tenant->id);

        // Assert: verify the found tenant's ID
        $this->assertEquals($tenant->id, $foundTenant->id);
    }

    /**
     * Test if trying to find a tenant by an invalid ID throws a ModelNotFoundException.
     */
    public function testThrowsExceptionIfTenantNotFoundById()
    {
        // Act & Assert: assert that the tenant is not found
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->tenantRepository->findById(999); // assuming 999 does not exist
    }

    /**
     * Test if we can find a tenant by its slug.
     */
    public function testCanFindTenantBySlug()
    {
        // Arrange: create a tenant
        $tenant = Tenant::factory()->create(['slug' => 'unique-slug']);

        // Act: find tenant by slug
        $foundTenant = $this->tenantRepository->findBySlug('unique-slug');

        // Assert: verify the found tenant's slug
        $this->assertEquals('unique-slug', $foundTenant->slug);
    }

    /**
     * Test if a new tenant can be created successfully.
     */
    public function testCanCreateTenant()
    {
        // Arrange: prepare tenant data
        $tenantData = [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'domain' => 'test.com',
            'settings' => json_encode(['key' => 'value']),
        ];

        // Act: create the tenant
        $tenant = $this->tenantRepository->create($tenantData);

        // Assert: check if the tenant was created
        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
    }

    /**
     * Test if we can update a tenant successfully.
     */
    public function testCanUpdateTenant()
    {
        // Arrange: create a tenant
        $tenant = Tenant::factory()->create();

        // Prepare new data
        $updatedData = [
            'name' => 'Updated Tenant Name',
        ];

        // Act: update the tenant
        $updatedTenant = $this->tenantRepository->update($tenant, $updatedData);

        // Assert: check if the tenant name was updated
        $this->assertEquals('Updated Tenant Name', $updatedTenant->name);
    }

    /**
     * Test if we can delete a tenant successfully.
     */
    public function testCanDeleteTenant()
    {
        // Arrange: create a tenant
        $tenant = Tenant::factory()->create();

        // Act: delete the tenant
        $this->tenantRepository->delete($tenant);

        // Assert: verify the tenant is deleted from the database
        $this->assertDatabaseMissing('tenants', [
            'id' => $tenant->id,
        ]);
    }
}
