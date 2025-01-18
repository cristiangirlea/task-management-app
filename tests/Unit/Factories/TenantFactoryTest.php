<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Tests\TestCase;

class TenantFactoryTest extends TestCase
{
    public function test_tenant_factory_creates_valid_tenant()
    {
        $tenant = Tenant::factory()->create();

        $this->assertNotEmpty($tenant->name);
        $this->assertNotEmpty($tenant->domain);
    }
}
