<?php

$keyId = env('RAZORPAY_KEY');
$keySecret = env('RAZORPAY_SECRET');
$explicitlyEnabled = filter_var(env('RAZORPAY_ENABLED'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

return [
    'enabled' => $explicitlyEnabled ?? filled($keyId) && filled($keySecret),
    'key' => $keyId,
    'secret' => $keySecret,
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    'currency' => strtoupper((string) env('RAZORPAY_CURRENCY', env('BILLING_CURRENCY', 'INR'))),
    'configured' => filled($keyId) && filled($keySecret) && ($explicitlyEnabled ?? (filled($keyId) && filled($keySecret))),
    'webhooks_configured' => filled(env('RAZORPAY_WEBHOOK_SECRET')),
];
