<?php

namespace Tests\Feature\Api;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\CreatesSubscriptions;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use ActsAsTenantUser, CreatesSubscriptions;

    public function test_a_new_workspace_is_on_the_free_plan(): void
    {
        $this->actingAsTenantUser();

        $this->getJson('/api/billing')
            ->assertOk()
            ->assertExactJson([
                'status' => 'success',
                'message' => __('billing.retrieved'),
                'data' => [
                    'plan' => 'free',
                    'status' => 'none',
                    'seats' => ['used' => 1, 'limit' => 3],
                    'free_seats' => 3,
                    'seat_price_cents' => 800,
                    'currency' => 'usd',
                    'ends_at' => null,
                    'has_payment_problem' => false,
                    'can_manage' => true,
                ],
            ]);
    }

    public function test_an_active_subscription_is_the_team_plan_without_a_seat_limit(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->subscribe($owner->tenant);

        $this->getJson('/api/billing')
            ->assertOk()
            ->assertJsonPath('data.plan', 'team')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.seats', ['used' => 1, 'limit' => null]);
    }

    public function test_a_cancelled_subscription_stays_team_until_the_paid_period_ends(): void
    {
        $owner = $this->actingAsTenantUser();
        $endsAt = now()->addDays(10)->startOfSecond();
        $this->subscribe($owner->tenant, ['ends_at' => $endsAt]);

        $this->getJson('/api/billing')
            ->assertJsonPath('data.plan', 'team')
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.ends_at', $endsAt->toIso8601String());
    }

    public function test_an_ended_subscription_falls_back_to_free(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->subscribe($owner->tenant, ['stripe_status' => 'canceled', 'ends_at' => now()->subDay()]);

        $this->getJson('/api/billing')
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.status', 'none')
            ->assertJsonPath('data.seats.limit', 3)
            ->assertJsonPath('data.ends_at', null);
    }

    /**
     * Stripe retries a failed card for a while; the team keeps its seats
     * and the owner is told to fix the payment method.
     */
    public function test_a_past_due_subscription_keeps_its_seats_and_flags_the_payment(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->subscribe($owner->tenant, ['stripe_status' => 'past_due']);

        $this->getJson('/api/billing')
            ->assertJsonPath('data.plan', 'team')
            ->assertJsonPath('data.status', 'past_due')
            ->assertJsonPath('data.has_payment_problem', true)
            ->assertJsonPath('data.seats.limit', null);
    }

    public function test_an_unfinished_checkout_is_not_a_subscription(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->subscribe($owner->tenant, ['stripe_status' => 'incomplete']);

        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'free');
    }

    public function test_members_can_read_the_plan_but_only_owners_change_it(): void
    {
        $this->actingAsTenantUser(null, ['role' => User::ROLE_MEMBER]);

        $this->getJson('/api/billing')->assertOk()->assertJsonPath('data.can_manage', false);
        $this->postJson('/api/billing/checkout')->assertForbidden();
        $this->postJson('/api/billing/portal')->assertForbidden();
    }

    public function test_a_user_without_a_workspace_is_refused(): void
    {
        $stray = User::factory()->create();
        $stray->forceFill(['tenant_id' => null])->save();
        Sanctum::actingAs($stray->fresh());

        $this->getJson('/api/billing')->assertForbidden();
        $this->postJson('/api/billing/checkout')->assertForbidden();
    }

    public function test_checkout_is_refused_when_already_subscribed(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->subscribe($owner->tenant);

        $this->postJson('/api/billing/checkout')
            ->assertStatus(409)
            ->assertJsonPath('message', __('billing.already_subscribed'));
    }

    public function test_the_portal_needs_a_stripe_customer(): void
    {
        $this->actingAsTenantUser();

        $this->postJson('/api/billing/portal')
            ->assertStatus(409)
            ->assertJsonPath('message', __('billing.no_customer'));
    }

    public function test_an_unconfigured_server_says_so_instead_of_failing(): void
    {
        $this->actingAsTenantUser();
        config(['billing.price_id' => null]);

        $this->postJson('/api/billing/checkout')
            ->assertStatus(503)
            ->assertJsonPath('message', __('billing.not_configured'));
    }

    public function test_the_workspace_resource_reports_the_plan_without_stripe_details(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->getJson('/api/tenant')->assertJsonPath('data.plan', 'free');

        $this->subscribe($owner->tenant);

        $this->getJson('/api/tenant')
            ->assertJsonPath('data.plan', 'team')
            ->assertJsonMissingPath('data.stripe_id');
    }

    public function test_member_changes_on_a_subscribed_workspace_update_the_stripe_quantity(): void
    {
        Mail::fake();
        $owner = $this->actingAsTenantUser();
        $this->subscribe($owner->tenant);
        $invitation = Invitation::factory()->create(['tenant_id' => $owner->tenant_id, 'invited_by' => $owner->id]);

        app('auth')->forgetGuards();
        $this->postJson("/api/invitations/{$invitation->token}/accept", [
            'name' => 'New Person',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        Sanctum::actingAs($owner);
        $member = User::where('email', $invitation->email)->firstOrFail();
        $this->deleteJson("/api/tenant/members/{$member->id}")->assertNoContent();

        $this->assertSame([
            ['tenant_id' => $owner->tenant_id, 'seats' => 2],
            ['tenant_id' => $owner->tenant_id, 'seats' => 1],
        ], $this->seatSync()->synced);
    }

    public function test_a_member_deleting_their_account_updates_the_stripe_quantity(): void
    {
        $owner = User::factory()->create();
        $this->subscribe($owner->tenant);
        $this->actingAsTenantUser($owner->tenant, ['role' => User::ROLE_MEMBER]);

        $this->deleteJson('/api/user')->assertNoContent();

        $this->assertSame([['tenant_id' => $owner->tenant_id, 'seats' => 1]], $this->seatSync()->synced);
    }

    public function test_member_changes_on_a_free_workspace_never_reach_stripe(): void
    {
        $owner = $this->actingAsTenantUser();
        $member = User::factory()->member()->create(['tenant_id' => $owner->tenant_id]);

        $this->deleteJson("/api/tenant/members/{$member->id}")->assertNoContent();

        $this->assertSame([], $this->seatSync()->synced);
    }
}
