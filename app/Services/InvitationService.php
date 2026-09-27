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
    public function __construct(protected BillingService $billing) {}

    /**
     * Invite an email address to a workspace and send the accept link.
     *
     * Pending invitations hold a seat, so a full free workspace is refused
     * here (402) rather than at accept time. The tenant row is locked so two
     * concurrent invitations cannot both take the last seat.
     */
    public function invite(Tenant $tenant, User $inviter, string $email): Invitation
    {
        $invitation = DB::transaction(function () use ($tenant, $inviter, $email): Invitation {
            $this->lockTenant($tenant->id);
            $this->billing->ensureCanInvite($tenant);

            return $tenant->invitations()->create([
                'email' => $email,
                'invited_by' => $inviter->id,
            ]);
        });

        $invitation->setRelation('tenant', $tenant)->setRelation('inviter', $inviter);

        Mail::to($email)->send(new WorkspaceInvitationMail($invitation));

        return $invitation;
    }

    /**
     * Create the invitee's account inside the workspace and consume the invitation.
     *
     * The seat is checked again here: the workspace may have filled up since
     * the invitation was sent (or been downgraded). When it has, this throws
     * a 402 and the invitation stays pending for after an upgrade.
     *
     * @param  array{name: string, password: string}  $data
     */
    public function accept(Invitation $invitation, array $data): User
    {
        $user = DB::transaction(function () use ($invitation, $data): User {
            $tenant = $this->lockTenant($invitation->tenant_id);
            $this->billing->ensureCanAccept($tenant);

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

        // Outside the transaction: a slow or failing Stripe call must not
        // hold the lock or undo the new membership.
        $this->billing->syncSeats($user->tenant);

        return $user;
    }

    private function lockTenant(int $tenantId): Tenant
    {
        return Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
    }
}
