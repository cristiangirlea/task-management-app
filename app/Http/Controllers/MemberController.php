<?php

namespace App\Http\Controllers;

use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Members of the current workspace.
 */
class MemberController extends ApiBaseController
{
    public function __construct(protected BillingService $billing) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $this->authorize('view', $tenant);

        $members = $tenant->users()->orderByRaw("case when role = 'owner' then 0 else 1 end")->orderBy('name')->get();

        return $this->respondApiSuccess(MemberResource::class, $members, 'Members retrieved successfully');
    }

    /**
     * Remove a member from the workspace. Accounts belong to exactly one
     * workspace, so this deletes the account; their tasks become unassigned.
     */
    public function destroy(Request $request, User $member): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $this->authorize('manage', $tenant);

        abort_unless($member->tenant_id === $tenant->id, 404);

        if ($member->is($request->user())) {
            return $this->respondApiError(__('member.destroy.self'), 422);
        }

        if ($member->isOwner()) {
            return $this->respondApiError(__('member.destroy.owner'), 422);
        }

        $member->tokens()->delete();
        $member->delete();

        $this->billing->syncSeats($tenant);

        return $this->respondApiSuccess(null, null, __('member.destroy.success'), 204);
    }
}
