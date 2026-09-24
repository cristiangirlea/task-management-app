<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Services\Billing\SeatSynchronizer;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Tests\Fakes\FakeSeatSynchronizer;

/**
 * Put a workspace on the Team plan the way Cashier's webhook would leave it,
 * without talking to Stripe.
 */
trait CreatesSubscriptions
{
    protected function subscribe(Tenant $tenant, array $attributes = []): Subscription
    {
        $tenant->forceFill(['stripe_id' => $tenant->stripe_id ?? 'cus_'.Str::random(10)])->save();

        return Subscription::create($attributes + [
            'tenant_id' => $tenant->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.Str::random(10),
            'stripe_status' => 'active',
            'stripe_price' => 'price_testing',
            'quantity' => $tenant->users()->count(),
        ]);
    }

    protected function seatSync(): FakeSeatSynchronizer
    {
        return $this->app->make(SeatSynchronizer::class);
    }
}
