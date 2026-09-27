<?php

namespace Tests\Fakes;

use App\Models\Tenant;
use App\Services\Billing\SeatSynchronizer;

/**
 * Records seat changes instead of sending them to Stripe. Like Cashier after
 * a successful update, it saves the new quantity locally, unless `failing`
 * is set to play Stripe being unreachable.
 */
class FakeSeatSynchronizer implements SeatSynchronizer
{
    /** @var list<array{tenant_id: int, seats: int}> */
    public array $synced = [];

    public bool $failing = false;

    public function sync(Tenant $tenant, int $seats): void
    {
        $this->synced[] = ['tenant_id' => $tenant->id, 'seats' => $seats];

        if (! $this->failing) {
            $tenant->subscription('default')?->forceFill(['quantity' => $seats])->save();
        }
    }
}
