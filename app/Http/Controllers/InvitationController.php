<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invitation\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pending invitations of the current workspace, owners only.
 *
 * Listing is owner-gated like creating and revoking, because an invitation's
 * accept link contains the token that authenticates the invitee: anyone who
 * can read it can consume the invitation and take the invited identity.
 */
class InvitationController extends ApiBaseController
{
    public function __construct(protected InvitationService $invitations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage', $request->user()->tenant);

        $pending = Invitation::pending()->with('inviter')->latest()->get();

        return $this->respondApiSuccess(InvitationResource::class, $pending, 'Invitations retrieved successfully');
    }

    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $invitation = $this->invitations->invite($tenant, $request->user(), $request->input('email'));
        $message = $invitation->emailSent ? __('invitation.store.success') : __('invitation.store.mail_failed');

        return $this->respondApiSuccess(InvitationResource::class, $invitation, $message, 201);
    }

    /**
     * Email the invitation again with a new link (the old one stops working).
     * The response carries the new link, the only time it can be read.
     */
    public function resend(Request $request, Invitation $invitation): JsonResponse
    {
        $this->authorize('manage', $request->user()->tenant);

        $invitation = $this->invitations->resend($invitation);
        $message = $invitation->emailSent ? __('invitation.resend.success') : __('invitation.resend.mail_failed');

        return $this->respondApiSuccess(InvitationResource::class, $invitation, $message);
    }

    public function destroy(Request $request, Invitation $invitation): JsonResponse
    {
        $this->authorize('manage', $request->user()->tenant);

        $invitation->delete();

        return $this->respondApiSuccess(null, null, __('invitation.destroy.success'), 204);
    }
}
