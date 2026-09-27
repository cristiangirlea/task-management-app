<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;

/**
 * The workspace's plan. Any member may read it; owners start a Stripe
 * Checkout to upgrade and open the Stripe billing portal to change the card,
 * see invoices or cancel. Stripe reports changes back through Cashier's
 * webhook at POST /api/stripe/webhook.
 */
class BillingController extends ApiBaseController
{
    public function __construct(protected BillingService $billing) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $this->authorize('view', $tenant);

        return $this->respondApiSuccess(null, $this->billing->summary($tenant, $request->user()), __('billing.retrieved'));
    }

    public function checkout(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $this->authorize('manage', $tenant);

        if (! $this->configured()) {
            return $this->respondApiError(__('billing.not_configured'), 503);
        }

        $url = $this->stripe(fn () => $this->billing->checkoutUrl(
            $tenant,
            $this->settingsUrl('?billing=success'),
            $this->settingsUrl('?billing=cancel'),
        ));

        return $this->respondApiSuccess(null, ['url' => $url], __('billing.checkout'));
    }

    public function portal(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $this->authorize('manage', $tenant);

        if (! $this->configured()) {
            return $this->respondApiError(__('billing.not_configured'), 503);
        }

        if (! $tenant->hasStripeId()) {
            return $this->respondApiError(__('billing.no_customer'), 409);
        }

        // The portal shows upcoming invoices, so make sure they bill the
        // current number of members.
        $this->billing->syncSeats($tenant);

        $url = $this->stripe(fn () => $tenant->billingPortalUrl($this->settingsUrl()));

        return $this->respondApiSuccess(null, ['url' => $url], __('billing.portal'));
    }

    /**
     * Stripe being down or refusing a request is a 502 the owner can act on
     * (try again), not an unexplained 500.
     */
    private function stripe(callable $call): mixed
    {
        try {
            return $call();
        } catch (ApiErrorException $e) {
            report($e);

            abort(502, __('billing.stripe_unavailable'));
        }
    }

    private function configured(): bool
    {
        return filled(config('cashier.secret')) && filled(config('billing.price_id'));
    }

    private function settingsUrl(string $query = ''): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/settings'.$query;
    }
}
