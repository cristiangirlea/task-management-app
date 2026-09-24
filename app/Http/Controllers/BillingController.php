<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if ($this->billing->isSubscribed($tenant)) {
            return $this->respondApiError(__('billing.already_subscribed'), 409);
        }

        $checkout = $tenant->newSubscription('default', config('billing.price_id'))
            ->quantity($this->billing->seatsUsed($tenant))
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => $this->settingsUrl('?billing=success'),
                'cancel_url' => $this->settingsUrl('?billing=cancel'),
            ]);

        return $this->respondApiSuccess(null, ['url' => $checkout->url], __('billing.checkout'));
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

        return $this->respondApiSuccess(null, ['url' => $tenant->billingPortalUrl($this->settingsUrl())], __('billing.portal'));
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
