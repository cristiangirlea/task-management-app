<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SeatSynchronizer;
use App\Services\Billing\StripeSeatSynchronizer;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\CreatesSubscriptions;
use Tests\TestCase;

/**
 * The paths that do call Stripe, run through the real Cashier code against
 * the stubbed Stripe API (tests/Fakes/StripeStub.php).
 */
class StripeCallsTest extends TestCase
{
    use ActsAsTenantUser, CreatesSubscriptions;

    private function useRealSeatSync(): void
    {
        $this->app->bind(SeatSynchronizer::class, StripeSeatSynchronizer::class);
    }

    private function subscriptionResponse(string $id, int $quantity): array
    {
        return ['id' => $id, 'object' => 'subscription', 'status' => 'active', 'quantity' => $quantity];
    }

    public function test_a_seat_change_updates_stripe_and_the_local_quantity(): void
    {
        $this->useRealSeatSync();
        $owner = $this->actingAsTenantUser();
        $subscription = $this->subscribe($owner->tenant, ['quantity' => 2]);
        $member = User::factory()->member()->create(['tenant_id' => $owner->tenant_id]);
        $this->stripe->on('POST', "/v1/subscriptions/{$subscription->stripe_id}", $this->subscriptionResponse($subscription->stripe_id, 1));

        $this->deleteJson("/api/tenant/members/{$member->id}")->assertNoContent();

        $this->assertSame(1, $subscription->refresh()->quantity);
        $this->assertEquals(1, $this->stripe->requests[0]['params']['quantity'] ?? null);
    }

    public function test_when_stripe_is_down_the_member_change_still_happens_and_is_logged(): void
    {
        $this->useRealSeatSync();
        Log::spy();
        $owner = $this->actingAsTenantUser();
        $subscription = $this->subscribe($owner->tenant, ['quantity' => 2]);
        $member = User::factory()->member()->create(['tenant_id' => $owner->tenant_id]);

        $this->deleteJson("/api/tenant/members/{$member->id}")->assertNoContent();

        $this->assertModelMissing($member);
        $this->assertSame(2, $subscription->refresh()->quantity, 'the stale quantity is what the reconcile command looks for');
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'seat count'))->once();
    }

    public function test_opening_the_portal_brings_the_seat_count_up_to_date_first(): void
    {
        $this->useRealSeatSync();
        $owner = $this->actingAsTenantUser();
        User::factory()->member()->create(['tenant_id' => $owner->tenant_id]);
        $subscription = $this->subscribe($owner->tenant, ['quantity' => 1]);
        $this->stripe
            ->on('POST', "/v1/subscriptions/{$subscription->stripe_id}", $this->subscriptionResponse($subscription->stripe_id, 2))
            ->on('POST', '/v1/billing_portal/sessions', ['id' => 'bps_test', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/test']);

        $this->postJson('/api/billing/portal')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://billing.stripe.com/p/session/test');

        $this->assertSame([
            "POST /v1/subscriptions/{$subscription->stripe_id}",
            'POST /v1/billing_portal/sessions',
        ], $this->stripe->calls());
        $this->assertSame(2, $subscription->refresh()->quantity);
    }

    public function test_checkout_bills_the_current_members_to_a_customer_in_the_owners_name(): void
    {
        $owner = $this->actingAsTenantUser(Tenant::factory()->create(['name' => 'Acme']), ['email' => 'owner@acme.test']);
        User::factory()->member()->count(3)->create(['tenant_id' => $owner->tenant_id]);
        $this->stripe
            ->on('POST', '/v1/customers', ['id' => 'cus_new', 'object' => 'customer'])
            ->on('POST', '/v1/checkout/sessions', ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test']);

        $this->postJson('/api/billing/checkout')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_test');

        [$customer, $session] = $this->stripe->requests;
        $this->assertSame('owner@acme.test', $customer['params']['email']);
        $this->assertSame('Acme', $customer['params']['name']);
        $this->assertSame('cus_new', $session['params']['customer']);
        $this->assertSame('price_testing', $session['params']['line_items'][0]['price']);
        $this->assertEquals(4, $session['params']['line_items'][0]['quantity']);
        $this->assertSame('http://localhost:3000/settings?billing=success', $session['params']['success_url']);
        $this->assertSame('cus_new', $owner->tenant->fresh()->stripe_id);
    }

    private function list(array $items): array
    {
        return ['object' => 'list', 'data' => $items, 'has_more' => false];
    }

    public function test_checkout_is_refused_while_stripe_already_has_a_subscription_for_the_workspace(): void
    {
        // Paid a moment ago; the webhook has not arrived, so locally it is still Free.
        $this->actingAsTenantUser(Tenant::factory()->create(['stripe_id' => 'cus_paid']));
        $this->stripe->on('GET', '/v1/subscriptions', $this->list([
            ['id' => 'sub_old', 'object' => 'subscription', 'status' => 'canceled'],
            ['id' => 'sub_new', 'object' => 'subscription', 'status' => 'incomplete'],
        ]));

        $this->postJson('/api/billing/checkout')
            ->assertStatus(409)
            ->assertJsonPath('message', __('billing.pending_subscription'));

        $this->assertSame(['GET /v1/subscriptions'], $this->stripe->calls());
    }

    public function test_a_new_checkout_expires_the_one_left_open_in_another_tab(): void
    {
        $this->actingAsTenantUser(Tenant::factory()->create(['stripe_id' => 'cus_known']));
        $this->stripe
            ->on('GET', '/v1/subscriptions', $this->list([['id' => 'sub_x', 'object' => 'subscription', 'status' => 'canceled']]))
            ->on('GET', '/v1/checkout/sessions', $this->list([['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'open']]))
            ->on('POST', '/v1/checkout/sessions/cs_old/expire', ['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'expired'])
            ->on('GET', '/v1/customers/cus_known', ['id' => 'cus_known', 'object' => 'customer'])
            ->on('POST', '/v1/checkout/sessions', ['id' => 'cs_new', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_new']);

        $this->postJson('/api/billing/checkout')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_new');

        $this->assertSame([
            'GET /v1/subscriptions',
            'GET /v1/checkout/sessions',
            'POST /v1/checkout/sessions/cs_old/expire',
            'GET /v1/customers/cus_known',
            'POST /v1/checkout/sessions',
        ], $this->stripe->calls());
    }

    public function test_stripe_being_unreachable_is_a_clear_502_not_a_500(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->subscribe($owner->tenant);

        $this->postJson('/api/billing/portal')
            ->assertStatus(502)
            ->assertJsonPath('message', __('billing.stripe_unavailable'));

        $this->actingAsTenantUser();
        $this->postJson('/api/billing/checkout')
            ->assertStatus(502)
            ->assertJsonPath('message', __('billing.stripe_unavailable'));
    }
}
