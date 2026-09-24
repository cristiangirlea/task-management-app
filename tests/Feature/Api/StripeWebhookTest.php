<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Subscription;
use Tests\Concerns\CreatesSubscriptions;
use Tests\TestCase;

/**
 * Stripe reports every subscription change to Cashier's webhook at
 * POST /api/stripe/webhook. These post signed payloads shaped like Stripe's
 * and check the workspace's plan follows.
 */
class StripeWebhookTest extends TestCase
{
    use CreatesSubscriptions;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['stripe_id' => 'cus_workspace']);
        User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * @param  array<string, mixed>  $object  overrides for the subscription object
     */
    private function event(string $type, array $object = []): array
    {
        return [
            'id' => 'evt_test',
            'type' => $type,
            'data' => ['object' => $object + [
                'id' => 'sub_workspace',
                'customer' => 'cus_workspace',
                'status' => 'active',
                'metadata' => ['type' => 'default'],
                'cancel_at_period_end' => false,
                'cancel_at' => null,
                'canceled_at' => null,
                'trial_end' => null,
                'items' => ['data' => [[
                    'id' => 'si_seats',
                    'price' => ['id' => 'price_testing', 'product' => 'prod_team'],
                    'quantity' => 1,
                ]]],
            ]],
        ];
    }

    private function seats(int $quantity): array
    {
        return ['items' => ['data' => [[
            'id' => 'si_seats',
            'price' => ['id' => 'price_testing', 'product' => 'prod_team'],
            'quantity' => $quantity,
        ]]]];
    }

    private function send(array $event, ?string $secret = 'whsec_testing'): TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($secret !== null) {
            $headers['HTTP_STRIPE_SIGNATURE'] = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
        }

        return $this->call('POST', '/api/stripe/webhook', server: $headers, content: $body);
    }

    private function subscription(): ?Subscription
    {
        return Subscription::where('stripe_id', 'sub_workspace')->first();
    }

    public function test_a_completed_checkout_puts_the_workspace_on_team(): void
    {
        $this->send($this->event('customer.subscription.created'))->assertOk();

        $subscription = $this->subscription();
        $this->assertSame($this->tenant->id, $subscription->tenant_id);
        $this->assertSame('default', $subscription->type);
        $this->assertTrue($this->tenant->fresh()->subscribed('default'));
    }

    public function test_a_quantity_change_is_recorded(): void
    {
        $this->subscribe($this->tenant, ['stripe_id' => 'sub_workspace', 'quantity' => 3]);

        $this->send($this->event('customer.subscription.updated', $this->seats(5)))->assertOk();

        $this->assertSame(5, $this->subscription()->quantity);
        $this->assertSame('active', $this->subscription()->stripe_status);
    }

    public function test_a_failed_renewal_marks_the_subscription_past_due(): void
    {
        $this->subscribe($this->tenant, ['stripe_id' => 'sub_workspace']);

        $this->send($this->event('customer.subscription.updated', ['status' => 'past_due']))->assertOk();

        $this->assertSame('past_due', $this->subscription()->stripe_status);
        $this->assertTrue($this->tenant->fresh()->hasIncompletePayment());
    }

    public function test_a_scheduled_cancellation_keeps_team_until_it_takes_effect(): void
    {
        $this->subscribe($this->tenant, ['stripe_id' => 'sub_workspace']);
        $endsAt = now()->addDays(12)->startOfSecond();

        $this->send($this->event('customer.subscription.updated', ['cancel_at' => $endsAt->getTimestamp()]))->assertOk();

        $subscription = $this->subscription();
        $this->assertTrue($subscription->ends_at->equalTo($endsAt));
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertTrue($this->tenant->fresh()->subscribed('default'));
    }

    public function test_a_deleted_subscription_returns_the_workspace_to_free(): void
    {
        $this->subscribe($this->tenant, ['stripe_id' => 'sub_workspace']);

        $this->send($this->event('customer.subscription.deleted', ['status' => 'canceled']))->assertOk();

        $this->assertFalse($this->tenant->fresh()->subscribed('default'));
    }

    public function test_an_event_for_an_unknown_customer_changes_nothing(): void
    {
        $this->subscribe($this->tenant, ['stripe_id' => 'sub_workspace', 'quantity' => 3]);

        $this->send($this->event('customer.subscription.updated', ['customer' => 'cus_stranger'] + $this->seats(9)))->assertOk();

        $this->assertSame(3, $this->subscription()->quantity);
    }

    public function test_unsigned_or_forged_events_are_rejected(): void
    {
        $this->subscribe($this->tenant, ['stripe_id' => 'sub_workspace', 'quantity' => 3]);
        $forged = $this->event('customer.subscription.updated', $this->seats(50));

        $this->send($forged, secret: null)->assertForbidden();
        $this->send($forged, secret: 'whsec_guessed')->assertForbidden();

        $this->assertSame(3, $this->subscription()->quantity);
    }
}
