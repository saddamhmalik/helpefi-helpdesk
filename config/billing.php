<?php

return [
    'default_plan' => env('BILLING_DEFAULT_PLAN', 'professional'),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'enterprise'),
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'cancellation_grace_days' => (int) env('BILLING_CANCELLATION_GRACE_DAYS', 3),
    'currency' => strtoupper((string) env('BILLING_CURRENCY', 'USD')),
    'stripe_plans' => [
        'starter' => env('STRIPE_PRICE_STARTER'),
        'professional' => env('STRIPE_PRICE_PROFESSIONAL'),
        'enterprise' => env('STRIPE_PRICE_ENTERPRISE'),
    ],
];
