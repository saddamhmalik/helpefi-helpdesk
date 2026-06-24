<?php

return [
    'sources' => [
        'contact' => 'Contact form',
        'homepage' => 'Homepage',
        'chatbot' => 'Marketing chatbot',
        'registration' => 'Registration',
    ],

    'intents' => [
        'sales' => 'Sales inquiry',
        'demo' => 'Demo request',
        'support' => 'Support',
        'partnership' => 'Partnership',
        'enterprise' => 'Enterprise',
        'newsletter' => 'Product updates',
        'chat' => 'Chatbot conversation',
        'incomplete_signup' => 'Incomplete signup',
        'other' => 'Other',
    ],

    'statuses' => [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'converted' => 'Converted',
        'closed' => 'Closed',
        'spam' => 'Spam',
    ],

    'capture' => [
        'ip_max_attempts' => (int) env('MARKETING_LEAD_IP_MAX', 8),
        'ip_decay_minutes' => (int) env('MARKETING_LEAD_IP_DECAY', 1),
        'email_max_attempts' => (int) env('MARKETING_LEAD_EMAIL_MAX', 5),
        'email_decay_minutes' => (int) env('MARKETING_LEAD_EMAIL_DECAY', 60),
    ],
];
