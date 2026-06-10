<?php

return [
    'closed_status_names' => [
        'done',
        'closed',
        'resolved',
        'complete',
        'completed',
        'cancelled',
        'canceled',
    ],

    'providers' => [
        'slack' => [
            'label' => 'Slack',
            'category' => 'collaboration',
            'events' => [
                ['value' => 'ticket.created', 'label' => 'Ticket created'],
                ['value' => 'ticket.updated', 'label' => 'Ticket updated'],
                ['value' => 'ticket.customer_message', 'label' => 'Customer message received'],
            ],
        ],
        'jira' => [
            'label' => 'Jira',
            'category' => 'development',
        ],
        'linear' => [
            'label' => 'Linear',
            'category' => 'development',
        ],
        'shopify' => [
            'label' => 'Shopify',
            'category' => 'commerce',
            'package' => 'shopify/shopify-api',
        ],
        'hubspot' => [
            'label' => 'HubSpot CRM',
            'category' => 'crm',
            'package' => 'hubspot/api-client',
        ],
        'salesforce' => [
            'label' => 'Salesforce',
            'category' => 'crm',
            'package' => 'omniphx/forrest',
        ],
        'microsoft_teams' => [
            'label' => 'Microsoft Teams',
            'category' => 'collaboration',
        ],
        'zapier' => [
            'label' => 'Zapier',
            'category' => 'automation',
        ],
    ],
];
