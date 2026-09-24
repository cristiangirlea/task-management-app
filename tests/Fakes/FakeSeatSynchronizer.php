<?php

namespace Tests\Fakes;

use App\Models\Tenant;
use App\Services\Billing\SeatSynchronizer;

/**
 * Records seat changes instead of sending them to Stripe.
 */
class FakeSeatSynchronizer implements SeatSynchronizer
{
    /** @var list<array{tenant_id: int, seats: int}> */
    public array $synced = [];

    public function sync(Tenant $tenant, int $seats): void
    {
        $this->synced[] = ['tenant_id' => $tenant->id, 'seats' => $seats];
    }
}
