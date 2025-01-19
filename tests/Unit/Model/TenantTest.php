<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes()
    {
        $tenant = new Tenant();

        $this->assertEquals(['name', 'slug', 'domain', 'settings'], $tenant->getFillable());
    }

    public function test_settings_cast()
    {
        $tenant = Tenant::factory()->create([
            'settings' => ['key' => 'value'],
        ]);

        $this->assertIsArray($tenant->settings);
        $this->assertEquals('value', $tenant->settings['key']);
    }

    public function test_users_relationship()
    {
        $tenant = Tenant::factory()->create();
        $users = User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->assertCount(3, $tenant->users);
        $this->assertInstanceOf(User::class, $tenant->users->first());
    }

    public function test_projects_relationship()
    {
        $tenant = Tenant::factory()->create();
        $projects = Project::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $this->assertCount(2, $tenant->projects);
        $this->assertInstanceOf(Project::class, $tenant->projects->first());
    }

    public function test_scope_by_domain()
    {
        $tenant = Tenant::factory()->create(['domain' => 'example.com']);

        $foundTenant = Tenant::byDomain('example.com')->first();
        $this->assertNotNull($foundTenant);
        $this->assertEquals($tenant->id, $foundTenant->id);
    }

    public function test_scope_by_slug()
    {
        $tenant = Tenant::factory()->create(['slug' => 'example-slug']);

        $foundTenant = Tenant::bySlug('example-slug')->first();
        $this->assertNotNull($foundTenant);
        $this->assertEquals($tenant->id, $foundTenant->id);
    }
}
