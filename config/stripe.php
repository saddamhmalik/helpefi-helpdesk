<?php

return [
    'enabled' => (bool) env('STRIPE_ENABLED', env('STRIPE_SECRET') !== null && env('STRIPE_SECRET') !== ''),
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => strtolower((string) env('STRIPE_CURRENCY', env('BILLING_CURRENCY', 'usd'))),
];
