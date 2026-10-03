<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Services\Billing\SeatSynchronizer;
use App\Services\Billing\StripeSeatSynchronizer;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SeatSynchronizer::class, StripeSeatSynchronizer::class);

        // Cashier checks webhook signatures only when it has a secret. In
        // production, rather than accept unsigned events that anyone could
        // forge, leave the webhook route unregistered until one is set.
        // (Here, not in boot(): Cashier registers its routes first.)
        if ($this->app->isProduction() && blank(config('cashier.webhook.secret'))) {
            Cashier::ignoreRoutes();
        }

        $this->configureOAuth();
    }

    /**
     * Passport issues OAuth tokens to MCP clients. Its own routes are left
     * out (routes/ai.php has the ones in use): its consent screen needs a
     * browser session on the API, and people sign in to the web app instead,
     * which asks for consent at /authorize. (Here, not in boot(), for the
     * same reason as Cashier above.)
     */
    protected function configureOAuth(): void
    {
        Passport::ignoreRoutes();
        // Shown on the consent screen (laravel/mcp would otherwise register
        // the scope as "Use MCP server").
        Passport::tokensCan([Registrar::OAUTH_SCOPE => 'Read and change the projects and tasks in your workspace, as you']);
        Passport::setDefaultScope([Registrar::OAUTH_SCOPE]);
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
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

        // Account changes and two-factor settings, each of which checks a
        // password or a code; per user, so guessing either through a stolen
        // token is slow.
        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));

        // The second sign-in step carries no email, so it is limited per
        // challenge (each also allows only 5 wrong codes) and, more loosely,
        // per IP: people signing in from one office must not share 5 a minute.
        RateLimiter::for('two-factor-login', fn (Request $request) => [
            Limit::perMinute(5)->by('challenge:'.hash('sha256', (string) $request->input('challenge'))),
            Limit::perMinute(30)->by($request->ip()),
        ]);

        // OAuth for MCP clients. Hosted clients (claude.ai, say) call from a
        // few shared addresses on behalf of all their users, so these are
        // loose; registering writes a row, so it is limited harder.
        RateLimiter::for('oauth', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('oauth-register', fn (Request $request) => Limit::perHour(60)->by($request->ip()));

        // Sending and re-sending invitations emails someone; per owner, so
        // people behind one office IP do not share a budget.
        RateLimiter::for('invitations', fn (Request $request) => [
            Limit::perMinute(10)->by('minute:'.$request->user()?->id),
            Limit::perHour(100)->by('hour:'.$request->user()?->id),
        ]);
    }
}
