<?php

return [
    'oidc_presets' => [
        'google' => [
            'label' => 'Google Workspace',
            'driver' => 'google',
        ],
        'azure' => [
            'label' => 'Microsoft Entra ID',
            'driver' => 'azure',
        ],
        'oidc' => [
            'label' => 'Generic OIDC',
            'driver' => 'oidc',
        ],
    ],

    'default_role' => 'agent',

    'allowed_domains' => [],
];
