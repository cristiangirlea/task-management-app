<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\TenantRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TenantService
{
    protected TenantRepository $tenantRepository;

    public function __construct(TenantRepository $tenantRepository)
    {
        $this->tenantRepository = $tenantRepository;
    }

    /**
     * Get all tenants.
     */
    public function getAllTenants(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->tenantRepository->getAll();
    }

    /**
     * Find a tenant by ID.
     * Throws ModelNotFoundException if tenant does not exist.
     */
    public function findTenantById(int $id): Tenant
    {
        try {
            return $this->tenantRepository->findById($id);
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException("Tenant not found with ID {$id}");
        }
    }

    /**
     * Create a new tenant.
     */
    public function createTenant(array $data): Tenant
    {
        // Handle default settings or empty array
        $data['settings'] = $data['settings'] ?? json_encode([]);

        return $this->tenantRepository->create($data);
    }

    /**
     * Update an existing tenant.
     */
    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        // Optionally handle data like settings, etc.
        $data['settings'] = $data['settings'] ?? $tenant->settings;

        return $this->tenantRepository->update($tenant, $data);
    }

    /**
     * Delete a tenant.
     */
    public function deleteTenant(Tenant $tenant): void
    {
        $this->tenantRepository->delete($tenant);
    }
}
