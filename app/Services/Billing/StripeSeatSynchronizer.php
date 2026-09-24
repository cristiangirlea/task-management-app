<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeSeatSynchronizer implements SeatSynchronizer
{
    /**
     * Stripe prorates the change. A failure is logged rather than thrown: the
     * member change has already happened and must not be rolled back because
     * Stripe was unreachable. The next change, or an owner opening the
     * billing portal, brings the quantity back in line.
     */
    public function sync(Tenant $tenant, int $seats): void
    {
        $subscription = $tenant->subscription('default');

        if ($subscription === null || $subscription->quantity === $seats) {
            return;
        }

        try {
            $subscription->updateQuantity($seats);
        } catch (Throwable $e) {
            Log::warning('Could not update the seat count in Stripe.', [
                'tenant_id' => $tenant->id,
                'seats' => $seats,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
