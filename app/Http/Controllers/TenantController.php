<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The authenticated user's own workspace. Tenants are created at
 * registration; there is deliberately no cross-tenant listing.
 */
class TenantController extends ApiBaseController
{
    public function __construct(protected TenantService $tenantService) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant()->withCount('users')->firstOrFail();

        return $this->respondApiSuccess(TenantResource::class, $tenant, __('tenant.show.success'));
    }

    public function update(UpdateTenantRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant()->firstOrFail();

        $tenant = $this->tenantService->updateTenant($tenant, $request->validated());
        $tenant->loadCount('users');

        return $this->respondApiSuccess(TenantResource::class, $tenant, __('tenant.update.success'));
    }
}
