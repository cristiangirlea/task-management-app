<?php

namespace App\Repositories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class TenantRepository
{
    /**
     * Get all tenants.
     */
    public function getAll(): Collection
    {
        return Tenant::all();
    }

    /**
     * Find a tenant by ID.
     */
    public function findById(int $id): Tenant
    {
        return Tenant::findOrFail($id);
    }

    /**
     * Find a tenant by its slug.
     */
    public function findBySlug(string $slug): Tenant
    {
        return Tenant::where('slug', $slug)->firstOrFail();
    }

    /**
     * Create a new tenant.
     */
    public function create(array $data): Tenant
    {
        return Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'] ?? null,
            'settings' => $data['settings'] ?? null,
        ]);
    }

    /**
     * Update an existing tenant.
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update([
            'name' => $data['name'] ?? $tenant->name,
            'slug' => $data['slug'] ?? $tenant->slug,
            'domain' => $data['domain'] ?? $tenant->domain,
            'settings' => $data['settings'] ?? $tenant->settings,
        ]);

        return $tenant;
    }

    /**
     * Delete a tenant.
     */
    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }
}
