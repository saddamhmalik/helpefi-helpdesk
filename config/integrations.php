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
            'events' => [
                ['value' => 'ticket.created', 'label' => 'Ticket created'],
                ['value' => 'ticket.updated', 'label' => 'Ticket updated'],
                ['value' => 'ticket.customer_message', 'label' => 'Customer message received'],
            ],
        ],
        'jira' => [
            'label' => 'Jira',
        ],
        'linear' => [
            'label' => 'Linear',
        ],
    ],
];
