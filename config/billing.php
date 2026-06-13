<?php

return [
    'default_plan' => env('BILLING_DEFAULT_PLAN', 'professional'),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'enterprise'),
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'cancellation_grace_days' => (int) env('BILLING_CANCELLATION_GRACE_DAYS', 3),
    'currency' => strtoupper((string) env('BILLING_CURRENCY', 'USD')),
    'india_currency' => strtoupper((string) env('BILLING_INDIA_CURRENCY', 'INR')),
    'india_country' => strtoupper((string) env('BILLING_INDIA_COUNTRY', 'IN')),
    'razorpay_plans' => [
        'starter' => env('RAZORPAY_PLAN_STARTER'),
        'professional' => env('RAZORPAY_PLAN_PROFESSIONAL'),
        'enterprise' => env('RAZORPAY_PLAN_ENTERPRISE'),
    ],
    'razorpay_plans_yearly' => [
        'starter' => env('RAZORPAY_PLAN_STARTER_YEARLY'),
        'professional' => env('RAZORPAY_PLAN_PROFESSIONAL_YEARLY'),
        'enterprise' => env('RAZORPAY_PLAN_ENTERPRISE_YEARLY'),
    ],
];
