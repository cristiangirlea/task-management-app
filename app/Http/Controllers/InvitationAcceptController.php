<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Http\Resources\InvitationPreviewResource;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;

/**
 * Public side of an invitation: preview it and accept it by creating an
 * account inside the inviting workspace.
 */
class InvitationAcceptController extends ApiBaseController
{
    public function __construct(protected InvitationService $invitations) {}

    public function show(string $token): JsonResponse
    {
        $invitation = $this->find($token)->load(['tenant', 'inviter']);

        return $this->respondApiSuccess(InvitationPreviewResource::class, $invitation, 'Invitation retrieved successfully');
    }

    public function store(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        $invitation = $this->find($token)->load('tenant');

        if ($invitation->status() !== 'pending') {
            return $this->respondApiError(__('invitation.accept.unavailable'), 410);
        }

        if (User::where('email', $invitation->email)->exists()) {
            return $this->respondApiError(__('invitation.accept.already_registered'), 422);
        }

        $user = $this->invitations->accept($invitation, $request->validated());

        return $this->respondApiSuccess(null, [
            'user' => new UserResource($user->load('tenant')),
            'token' => $user->createToken('auth_token')->plainTextToken,
        ], __('invitation.accept.success', ['workspace' => $invitation->tenant->name]), 201);
    }

    private function find(string $token): Invitation
    {
        return Invitation::findByToken($token) ?? abort(404, 'Invitation not found.');
    }
}
