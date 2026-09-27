<?php

namespace App\Services;

use App\Exceptions\SeatLimitReachedException;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SeatSynchronizer;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Plans and seats. Free workspaces hold up to `billing.free_seats` members;
 * a subscription (Team) lifts the limit and is billed per member.
 *
 * "Subscribed" is Cashier's subscribed(): active, past due (see
 * AppServiceProvider), or cancelled but still inside the paid period.
 * Only syncSeats() and checkoutUrl() call Stripe.
 */
class BillingService
{
    public const PLAN_FREE = 'free';

    public const PLAN_TEAM = 'team';

    public function __construct(protected SeatSynchronizer $seats) {}

    public function isSubscribed(Tenant $tenant): bool
    {
        return $tenant->subscribed('default');
    }

    public function plan(Tenant $tenant): string
    {
        return $this->isSubscribed($tenant) ? self::PLAN_TEAM : self::PLAN_FREE;
    }

    public function seatsUsed(Tenant $tenant): int
    {
        return $tenant->users()->count();
    }

    /**
     * Members plus invitations still waiting to be accepted, so a free
     * workspace cannot queue more invitations than it has room for.
     */
    public function seatsReserved(Tenant $tenant): int
    {
        return $this->seatsUsed($tenant) + $this->pendingInvitations($tenant);
    }

    public function pendingInvitations(Tenant $tenant): int
    {
        return $tenant->invitations()
            ->withoutGlobalScope(TenantScope::class)
            ->pending()
            ->count();
    }

    /**
     * Null when there is no limit.
     */
    public function seatLimit(Tenant $tenant): ?int
    {
        return $this->isSubscribed($tenant) ? null : (int) config('billing.free_seats');
    }

    public function ensureCanInvite(Tenant $tenant): void
    {
        $limit = $this->seatLimit($tenant);

        if ($limit !== null && $this->seatsReserved($tenant) >= $limit) {
            throw new SeatLimitReachedException($limit);
        }
    }

    public function ensureCanAccept(Tenant $tenant): void
    {
        $limit = $this->seatLimit($tenant);

        if ($limit !== null && $this->seatsUsed($tenant) >= $limit) {
            throw new SeatLimitReachedException($limit);
        }
    }

    /**
     * Bring the subscription quantity in line with the member count. Stripe's
     * webhook then writes the confirmed quantity back to `subscriptions`.
     */
    public function syncSeats(Tenant $tenant): void
    {
        if ($this->isSubscribed($tenant)) {
            $this->seats->sync($tenant, $this->seatsUsed($tenant));
        }
    }

    /**
     * A Stripe Checkout page for the Team plan, guarded against paying twice:
     * concurrent requests are serialised on the tenant row, a subscription
     * Stripe has but whose webhook has not arrived is a 409, and any older
     * checkout still open is expired.
     */
    public function checkoutUrl(Tenant $tenant, string $successUrl, string $cancelUrl): string
    {
        return DB::transaction(function () use ($tenant, $successUrl, $cancelUrl): string {
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            if ($this->isSubscribed($tenant)) {
                throw new HttpException(409, __('billing.already_subscribed'));
            }

            if ($tenant->hasStripeId()) {
                $this->ensureNoSubscriptionInStripe($tenant);
                $this->expireOpenCheckouts($tenant);
            }

            return $tenant->newSubscription('default', config('billing.price_id'))
                ->quantity($this->seatsUsed($tenant))
                ->allowPromotionCodes()
                ->checkout(['success_url' => $successUrl, 'cancel_url' => $cancelUrl])
                ->url;
        });
    }

    private function ensureNoSubscriptionInStripe(Tenant $tenant): void
    {
        $subscriptions = $tenant->stripe()->subscriptions->all([
            'customer' => $tenant->stripe_id,
            'status' => 'all',
            'limit' => 100,
        ]);

        foreach ($subscriptions->data as $subscription) {
            if (! in_array($subscription->status, ['canceled', 'incomplete_expired'], true)) {
                throw new HttpException(409, __('billing.pending_subscription'));
            }
        }
    }

    private function expireOpenCheckouts(Tenant $tenant): void
    {
        $sessions = $tenant->stripe()->checkout->sessions->all([
            'customer' => $tenant->stripe_id,
            'status' => 'open',
            'limit' => 100,
        ]);

        foreach ($sessions->data as $session) {
            $tenant->stripe()->checkout->sessions->expire($session->id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant, User $viewer): array
    {
        $subscription = $tenant->subscription('default');
        $subscribed = $this->isSubscribed($tenant);

        return [
            'plan' => $subscribed ? self::PLAN_TEAM : self::PLAN_FREE,
            'status' => $this->status($tenant),
            'seats' => [
                'used' => $this->seatsUsed($tenant),
                // Held by invitations not yet accepted; they count toward the free limit.
                'pending' => $this->pendingInvitations($tenant),
                'limit' => $this->seatLimit($tenant),
            ],
            'free_seats' => (int) config('billing.free_seats'),
            'seat_price_cents' => (int) config('billing.seat_price_cents'),
            'currency' => (string) config('billing.currency'),
            'ends_at' => $subscribed ? $subscription?->ends_at?->toIso8601String() : null,
            'has_payment_problem' => $subscribed && $tenant->hasIncompletePayment(),
            'can_manage' => $viewer->can('manage', $tenant),
        ];
    }

    /**
     * none: never subscribed or the subscription has ended.
     * canceled: cancelled, still paid up until `ends_at`.
     */
    private function status(Tenant $tenant): string
    {
        $subscription = $tenant->subscription('default');

        return match (true) {
            ! $this->isSubscribed($tenant) => 'none',
            $subscription->onGracePeriod() => 'canceled',
            $subscription->pastDue() => 'past_due',
            default => 'active',
        };
    }
}
