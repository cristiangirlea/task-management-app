<?php

namespace Tests;

use App\Services\Billing\SeatSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeSeatSynchronizer;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No test talks to Stripe: seat changes are recorded instead.
        $this->app->instance(SeatSynchronizer::class, new FakeSeatSynchronizer);
    }
}
