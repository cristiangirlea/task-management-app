<?php

namespace App\Services;

use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InvitationService
{
    public function __construct(protected BillingService $billing) {}

    /**
     * Invite an email address to a workspace and send the accept link.
     *
     * Pending invitations hold a seat, so a full free workspace is refused
     * here (402) rather than at accept time. The tenant row is locked so two
     * concurrent invitations cannot both take the last seat.
     *
     * If the email cannot be sent the invitation still stands: the caller
     * gets the link (emailSent = false) to share another way.
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
        $invitation->emailSent = $this->send($invitation);

        return $invitation;
    }

    /**
     * Email a pending invitation again with a new link. The previous link
     * stops working (only one token hash is kept) and the invitation gets a
     * fresh expiry. It already holds its seat, so seats are not checked.
     *
     * The row is locked so a concurrent resend or revoke is seen: a revoked
     * invitation is 410, not re-sent.
     */
    public function resend(Invitation $invitation): Invitation
    {
        $invitation = DB::transaction(function () use ($invitation): Invitation {
            $locked = Invitation::whereKey($invitation->id)->lockForUpdate()->first();

            abort_unless($locked?->status() === 'pending', 410, __('invitation.resend.unavailable'));
            abort_if(User::where('email', $locked->email)->exists(), 422, __('invitation.resend.registered'));

            $locked->issueToken();
            $locked->expires_at = now()->addDays(Invitation::LIFETIME_DAYS);
            $locked->save();

            return $locked;
        });

        $invitation->load(['tenant', 'inviter']);
        $invitation->emailSent = $this->send($invitation);

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

    /**
     * Sent inline, so a mail provider failure surfaces here. It is reported
     * rather than thrown: the invitation exists and its link still works.
     */
    private function send(Invitation $invitation): bool
    {
        try {
            Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function lockTenant(int $tenantId): Tenant
    {
        return Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
    }
}
