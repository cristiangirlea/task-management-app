<?php

/*
|--------------------------------------------------------------------------
| Plans
|--------------------------------------------------------------------------
|
| Free workspaces hold up to `free_seats` members. The Team plan lifts the
| limit and is billed per member per month through one Stripe Price whose
| subscription quantity tracks the member count. Stripe keys and the
| webhook path live in config/cashier.php.
|
*/

return [
    'free_seats' => (int) env('BILLING_FREE_SEATS', 3),

    // A recurring, monthly, per-unit Price in Stripe.
    'price_id' => env('STRIPE_PRICE_ID'),

    // Shown to customers only; Stripe charges whatever the Price says.
    'seat_price_cents' => (int) env('BILLING_SEAT_PRICE_CENTS', 800),

    'currency' => env('CASHIER_CURRENCY', 'usd'),
];
