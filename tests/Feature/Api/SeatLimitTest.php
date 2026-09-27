<?php

namespace Tests\Feature\Api;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\CreatesSubscriptions;
use Tests\TestCase;

/**
 * A free workspace holds `billing.free_seats` (3) people, counting pending
 * invitations; a subscribed one has no limit. Exceeding the limit is a 402.
 */
class SeatLimitTest extends TestCase
{
    use ActsAsTenantUser, CreatesSubscriptions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->owner = $this->actingAsTenantUser();
    }

    private function addMembers(int $count): void
    {
        User::factory()->member()->count($count)->create(['tenant_id' => $this->owner->tenant_id]);
    }

    private function pendingInvitation(string $email): Invitation
    {
        return Invitation::factory()->create([
            'tenant_id' => $this->owner->tenant_id,
            'invited_by' => $this->owner->id,
            'email' => $email,
        ]);
    }

    private function accept(Invitation $invitation)
    {
        app('auth')->forgetGuards();

        return $this->postJson("/api/invitations/{$invitation->token}/accept", [
            'name' => 'New Person',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);
    }

    public function test_a_free_workspace_fills_up_and_the_next_invitation_is_a_402(): void
    {
        $this->postJson('/api/tenant/invitations', ['email' => 'two@example.com'])->assertCreated();
        $this->postJson('/api/tenant/invitations', ['email' => 'three@example.com'])->assertCreated();

        $this->postJson('/api/tenant/invitations', ['email' => 'four@example.com'])
            ->assertStatus(402)
            ->assertExactJson([
                'status' => 'error',
                'message' => __('billing.seat_limit', ['limit' => 3]),
            ]);

        $this->assertDatabaseMissing('invitations', ['email' => 'four@example.com']);
        Mail::assertSentCount(2);
    }

    public function test_pending_invitations_hold_a_seat_but_expired_ones_do_not(): void
    {
        $this->addMembers(1);
        $this->pendingInvitation('pending@example.com');

        $this->postJson('/api/tenant/invitations', ['email' => 'next@example.com'])->assertStatus(402);

        Invitation::withoutGlobalScopes()->where('email', 'pending@example.com')->update(['expires_at' => now()->subDay()]);

        $this->postJson('/api/tenant/invitations', ['email' => 'next@example.com'])->assertCreated();
    }

    public function test_accepting_into_a_full_workspace_is_refused_and_the_invitation_survives(): void
    {
        // Sent while there was room; the workspace filled up afterwards.
        $invitation = $this->pendingInvitation('late@example.com');
        $this->addMembers(2);

        $this->accept($invitation)
            ->assertStatus(402)
            ->assertJsonPath('message', __('billing.seat_limit', ['limit' => 3]));

        $this->assertDatabaseMissing('users', ['email' => 'late@example.com']);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_the_invitation_can_be_accepted_once_a_seat_frees_up(): void
    {
        $invitation = $this->pendingInvitation('late@example.com');
        $this->addMembers(2);
        $this->accept($invitation)->assertStatus(402);

        User::where('tenant_id', $this->owner->tenant_id)->where('role', User::ROLE_MEMBER)->first()->delete();

        $this->accept($invitation)->assertCreated();
    }

    public function test_removing_a_member_frees_a_seat(): void
    {
        $this->addMembers(2);
        $this->postJson('/api/tenant/invitations', ['email' => 'next@example.com'])->assertStatus(402);

        $member = User::where('tenant_id', $this->owner->tenant_id)->where('role', User::ROLE_MEMBER)->first();
        $this->deleteJson("/api/tenant/members/{$member->id}")->assertNoContent();

        $this->postJson('/api/tenant/invitations', ['email' => 'next@example.com'])->assertCreated();
    }

    public function test_the_limit_comes_from_configuration(): void
    {
        config(['billing.free_seats' => 5]);
        $this->addMembers(3);

        $this->postJson('/api/tenant/invitations', ['email' => 'five@example.com'])->assertCreated();
        $this->postJson('/api/tenant/invitations', ['email' => 'six@example.com'])
            ->assertStatus(402)
            ->assertJsonPath('message', __('billing.seat_limit', ['limit' => 5]));
    }

    public function test_a_subscribed_workspace_has_no_seat_limit(): void
    {
        $this->subscribe($this->owner->tenant);
        $this->addMembers(2);
        $invitation = $this->pendingInvitation('queued@example.com');

        $this->postJson('/api/tenant/invitations', ['email' => 'more@example.com'])->assertCreated();
        $this->accept($invitation)->assertCreated();

        $this->assertSame(4, User::where('tenant_id', $this->owner->tenant_id)->count());
    }

    public function test_one_workspace_being_full_does_not_affect_another(): void
    {
        $this->addMembers(2);
        $other = Tenant::factory()->create();
        $otherOwner = User::factory()->create(['tenant_id' => $other->id]);

        $this->actingAs($otherOwner, 'sanctum');
        $this->postJson('/api/tenant/invitations', ['email' => 'elsewhere@example.com'])->assertCreated();
    }
}
