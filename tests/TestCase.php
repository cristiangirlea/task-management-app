<?php

namespace Tests;

use App\Services\Billing\SeatSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeSeatSynchronizer;
use Tests\Fakes\StripeStub;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Stripe's HTTP API for this test; unstubbed requests fail as if Stripe were down. */
    protected StripeStub $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        // No test talks to Stripe: seat changes are recorded instead, and
        // anything else that reaches for Stripe hits the stub.
        $this->app->instance(SeatSynchronizer::class, new FakeSeatSynchronizer);
        $this->stripe = StripeStub::install();
    }
}
