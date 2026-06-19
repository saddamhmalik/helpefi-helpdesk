<?php

return [
    'limits' => [
        'agents' => [
            'label' => 'Team members',
            'description' => 'Maximum agents and pending invites combined.',
            'allow_unlimited' => true,
            'min' => 1,
            'max' => 10000,
        ],
        'tickets_monthly' => [
            'label' => 'Tickets per month',
            'description' => 'Maximum new tickets created each calendar month.',
            'allow_unlimited' => true,
            'min' => 1,
            'max' => 1000000,
        ],
    ],
    'features' => [
        'automation' => 'Automation & macros',
        'service_catalog' => 'Service catalog',
        'channels' => 'Live chat & channels',
        'sla' => 'SLA & business hours',
        'workspace' => 'Multi-brand workspace',
        'ai' => 'AI assist',
        'integrations' => 'Integrations & webhooks',
        'assets' => 'Asset management',
        'custom_domain' => 'Custom workspace domain',
        'sso' => 'SSO (SAML / OIDC)',
        'byo_database' => 'Bring your own database',
        'byo_storage' => 'Bring your own storage',
    ],
];
