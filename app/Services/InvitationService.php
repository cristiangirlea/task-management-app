<?php

namespace App\Services;

use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InvitationService
{
    /**
     * Invite an email address to a workspace and send the accept link.
     */
    public function invite(Tenant $tenant, User $inviter, string $email): Invitation
    {
        $invitation = $tenant->invitations()->create([
            'email' => $email,
            'invited_by' => $inviter->id,
        ]);

        $invitation->setRelation('tenant', $tenant)->setRelation('inviter', $inviter);

        Mail::to($email)->send(new WorkspaceInvitationMail($invitation));

        return $invitation;
    }

    /**
     * Create the invitee's account inside the workspace and consume the invitation.
     *
     * @param  array{name: string, password: string}  $data
     */
    public function accept(Invitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data): User {
            $user = User::create([
                'tenant_id' => $invitation->tenant_id,
                'role' => User::ROLE_MEMBER,
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
            ]);

            // Accepting the emailed invitation proves control of the address.
            $user->markEmailAsVerified();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
