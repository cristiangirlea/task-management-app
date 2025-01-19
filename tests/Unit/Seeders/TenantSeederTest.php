<?php

namespace Tests\Unit\Seeders;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSeederTest extends TestCase
{
    use RefreshDatabase;  // Ensures the database is reset for each test

    public function testTenantSeederCreatesTenants()
    {
        // Run the seeder for the first time
        Artisan::call('db:seed', ['--class' => 'TenantSeeder']);

        // Assert that 10 tenants were created
        $this->assertDatabaseCount('tenants', 10);

        // Check if any tenant data exists in the database
        // Since we're using Faker, we should just check that a tenant exists
        $this->assertDatabaseHas('tenants', [
            'name' => Tenant::first()->name, // Check if any tenant was created by verifying the first one
        ]);
    }

    public function testTenantSeederCreatesUnique_data()
    {
        // Reset the database and run the seeder again to add 10 more tenants
        Artisan::call('db:seed', ['--class' => 'TenantSeeder']);
        Artisan::call('db:seed', ['--class' => 'TenantSeeder']);  // Running it twice

        // Assert that 20 tenants now exist
        $this->assertDatabaseCount('tenants', 20); // Should have 20 tenants now

        // Optionally, check if the slugs are unique
        $tenants = Tenant::all();
        $slugs = $tenants->pluck('slug')->toArray();
        $this->assertCount(count($slugs), array_unique($slugs)); // Ensure all slugs are unique
    }
}
