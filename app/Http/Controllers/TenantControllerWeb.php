<?php

namespace App\Http\Controllers;

use App\Helpers\ResourceHelper;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantControllerWeb extends ApiBaseController
{
    /**
     * Display a listing of tenants.
     */
    public function index()
    {
        return $this->respondApiSuccess(TenantResource::class, Tenant::all(), 'Tenants retrieved successfully');
    }

    /**
     * Store a new tenant.
     */
    public function store(Request $request)
    {
        $tenant = Tenant::create($request->validated());

        return $this->respondApiSuccess(TenantResource::class, $tenant, 'Tenant created successfully', 201);
    }

    /**
     * Display a specific tenant.
     */
    public function show(Tenant $tenant)
    {
        return $this->respondApiSuccess(TenantResource::class, $tenant, 'Tenant retrieved successfully');
    }

    /**
     * Update a tenant.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $tenant->update($request->validated());

        return $this->respondApiSuccess(TenantResource::class, $tenant, 'Tenant updated successfully');
    }

    /**
     * Delete a tenant.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return $this->respondApiSuccess(null, null, 'Tenant deleted successfully', 204);
    }
}
