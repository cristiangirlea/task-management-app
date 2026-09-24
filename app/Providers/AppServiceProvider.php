<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Services\Billing\SeatSynchronizer;
use App\Services\Billing\StripeSeatSynchronizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SeatSynchronizer::class, StripeSeatSynchronizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureBilling();
        $this->configureRateLimiting();
    }

    /**
     * The workspace is the Stripe customer. A past-due subscription keeps its
     * seats while Stripe retries the card; Cashier's default would drop a
     * paying team to the free limit on the first failed charge.
     */
    protected function configureBilling(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        Cashier::keepPastDueSubscriptionsActive();
    }

    /**
     * Throttle the unauthenticated endpoints an attacker would hammer:
     * credential stuffing on login, mass signups, and mail floods through
     * the reset and verification senders.
     */
    protected function configureRateLimiting(): void
    {
        // Per credential and per IP, so one attacker cannot lock out a victim
        // by exhausting the limit on their email alone.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Sending mail is expensive and abusable.
        RateLimiter::for('mail', fn (Request $request) => [
            Limit::perMinute(3)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()));
    }
}
