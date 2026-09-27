<?php

namespace App\Services\Billing;

use App\Models\Tenant;

/**
 * Tells the payment provider how many seats a subscribed workspace uses.
 * An interface so tests can observe the call without reaching Stripe.
 */
interface SeatSynchronizer
{
    public function sync(Tenant $tenant, int $seats): void;
}
