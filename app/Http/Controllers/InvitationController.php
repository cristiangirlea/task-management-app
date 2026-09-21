<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invitation\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pending invitations of the current workspace. Any member can see them;
 * only owners can create or revoke them (TenantPolicy::manage).
 */
class InvitationController extends ApiBaseController
{
    public function __construct(protected InvitationService $invitations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user()->tenant);

        $pending = Invitation::pending()->with('inviter')->latest()->get();

        return $this->respondApiSuccess(InvitationResource::class, $pending, 'Invitations retrieved successfully');
    }

    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $this->authorize('manage', $tenant);

        $invitation = $this->invitations->invite($tenant, $request->user(), $request->input('email'));

        return $this->respondApiSuccess(InvitationResource::class, $invitation, __('invitation.store.success'), 201);
    }

    public function destroy(Request $request, Invitation $invitation): JsonResponse
    {
        $this->authorize('manage', $request->user()->tenant);

        $invitation->delete();

        return $this->respondApiSuccess(null, null, __('invitation.destroy.success'), 204);
    }
}
