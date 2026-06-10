<?php

$secret = env('STRIPE_SECRET');
$explicitlyEnabled = filter_var(env('STRIPE_ENABLED'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

return [
    'enabled' => $explicitlyEnabled ?? filled($secret),
    'key' => env('STRIPE_KEY'),
    'secret' => $secret,
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => strtolower((string) env('STRIPE_CURRENCY', env('BILLING_CURRENCY', 'usd'))),
    'configured' => filled($secret) && ($explicitlyEnabled ?? filled($secret)),
    'webhooks_configured' => filled(env('STRIPE_WEBHOOK_SECRET')),
];
