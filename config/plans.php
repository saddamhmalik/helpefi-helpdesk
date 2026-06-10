<?php

return [
    'starter' => [
        'name' => 'Starter',
        'price' => 29,
        'price_yearly' => 290,
        'limits' => [
            'agents' => 3,
            'tickets_monthly' => 50,
        ],
        'features' => [],
    ],
    'professional' => [
        'name' => 'Professional',
        'price' => 79,
        'price_yearly' => 790,
        'limits' => [
            'agents' => 15,
            'tickets_monthly' => 500,
        ],
        'features' => [
            'automation',
            'service_catalog',
            'channels',
            'sla',
            'workspace',
        ],
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'price' => 199,
        'price_yearly' => 1990,
        'limits' => [
            'agents' => null,
            'tickets_monthly' => null,
        ],
        'features' => [
            'automation',
            'service_catalog',
            'channels',
            'sla',
            'workspace',
            'ai',
            'integrations',
            'assets',
            'custom_domain',
            'sso',
        ],
    ],
];
