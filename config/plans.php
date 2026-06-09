<?php

return [
    'starter' => [
        'name' => 'Starter',
        'price' => 29,
        'limits' => [
            'agents' => 3,
            'tickets_monthly' => 50,
        ],
        'features' => [],
    ],
    'professional' => [
        'name' => 'Professional',
        'price' => 79,
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
        ],
    ],
];
