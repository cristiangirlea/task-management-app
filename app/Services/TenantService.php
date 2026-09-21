<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\TenantRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TenantService
{
    public function __construct(protected TenantRepository $tenantRepository) {}

    public function getAllTenants(): Collection
    {
        return $this->tenantRepository->getAll();
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findTenantById(int $id): Tenant
    {
        try {
            return $this->tenantRepository->findById($id);
        } catch (ModelNotFoundException) {
            throw new ModelNotFoundException("Tenant not found with ID {$id}");
        }
    }

    /**
     * Create a tenant; the slug is derived from the name when not given.
     */
    public function createTenant(array $data): Tenant
    {
        $data['slug'] = $data['slug'] ?? Tenant::uniqueSlug($data['name']);
        $data['settings'] = $data['settings'] ?? [];

        return $this->tenantRepository->create($data);
    }

    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        $data['settings'] = $data['settings'] ?? $tenant->settings;

        return $this->tenantRepository->update($tenant, $data);
    }

    public function deleteTenant(Tenant $tenant): void
    {
        $this->tenantRepository->delete($tenant);
    }
}
