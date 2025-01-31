<?php

namespace Tests\Unit\Factories;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_factory_creates_valid_tenant()
    {
        $tenant = Tenant::factory()->create();

        // Assert that the tenant has all the necessary attributes
        $this->assertNotEmpty($tenant->name);
        $this->assertNotEmpty($tenant->domain);
        $this->assertNotEmpty($tenant->slug);

        // Assert that settings attribute is an array
        $this->assertIsArray($tenant->settings);

        // Check that the tenant's slug is unique (based on the factory data)
        $anotherTenant = Tenant::factory()->create();
        $this->assertNotEquals($tenant->slug, $anotherTenant->slug);
    }

    public function test_tenant_factory_creates_tenant_with_valid_defaults()
    {
        // Create a tenant using the factory
        $tenant = Tenant::factory()->create();

        // Assert default data from the factory (example with fake data)
        $this->assertMatchesRegularExpression('/[a-z0-9\-]+/', $tenant->slug);
        $this->assertMatchesRegularExpression('/[a-z0-9\-]+/', $tenant->domain);
    }
}
