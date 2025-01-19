<?php

namespace App\Repositories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class TenantRepository
{
    /**
     * Get all tenants.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Tenant::all();
    }

    /**
     * Find a tenant by ID.
     *
     * @param int $id
     * @return Tenant
     */
    public function findById(int $id): Tenant
    {
        return Tenant::findOrFail($id);
    }

    /**
     * Find a tenant by its slug.
     *
     * @param string $slug
     * @return Tenant
     */
    public function findBySlug(string $slug): Tenant
    {
        return Tenant::where('slug', $slug)->firstOrFail();
    }

    /**
     * Create a new tenant.
     *
     * @param array $data
     * @return Tenant
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
     *
     * @param Tenant $tenant
     * @param array $data
     * @return Tenant
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
     *
     * @param Tenant $tenant
     * @return void
     */
    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }
}
