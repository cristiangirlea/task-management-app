<?php

namespace App\Services;

use App\Exceptions\SeatLimitReachedException;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SeatSynchronizer;

/**
 * Plans and seats. Free workspaces hold up to `billing.free_seats` members;
 * a subscription (Team) lifts the limit and is billed per member.
 *
 * "Subscribed" is Cashier's subscribed(): active, past due (see
 * AppServiceProvider), or cancelled but still inside the paid period.
 * Nothing here calls Stripe except syncSeats().
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
        return $this->seatsUsed($tenant) + $tenant->invitations()
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
