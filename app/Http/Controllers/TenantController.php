<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Responses\TenantResponseHandler;
use App\Services\TenantService;

class TenantController extends ApiBaseController
{
    private TenantService $tenantService;
    private TenantResponseHandler $responseHandler;

    public function __construct(TenantService $tenantService, TenantResponseHandler
    $responseHandler)
    {
        $this->tenantService = $tenantService;
        $this->responseHandler = $responseHandler
        ;
    }

    public function store(StoreTenantRequest $request): \Illuminate\Http\JsonResponse
    {
        return $this->responseHandler->handle(function () use ($request) {
            $tenant = $this->tenantService->createTenant($request->validated());
            return new TenantResource($tenant);
        }, null, 'store');
    }


    public function update(UpdateTenantRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->responseHandler->handle(function () use ($request, $id) {
            $tenant = $this->tenantService->updateTenant($id, $request->validated());
            return new TenantResource($tenant);
        }, null, 'update');
    }


    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        return $this->responseHandler->handle(
            fn () => $this->tenantService->deleteTenant($id),
            'Tenant',
            'destroy'
        );
    }

    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        return $this->responseHandler->handle(
            fn () => new TenantResource($this->tenantService->findTenantById($id)),
            'Tenant',
            'show'
        );
    }
}
