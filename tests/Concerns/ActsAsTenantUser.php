<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait ActsAsTenantUser
{
    /**
     * Authenticate a user (in a fresh or given tenant) for the next requests.
     */
    protected function actingAsTenantUser(?Tenant $tenant = null, array $attributes = []): User
    {
        $tenant ??= Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id] + $attributes);

        Sanctum::actingAs($user);

        return $user;
    }
}
