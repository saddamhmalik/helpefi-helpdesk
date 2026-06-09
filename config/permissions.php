<?php

return [
    'groups' => [
        'General' => [
            'access.agent' => 'Access agent portal',
        ],
        'Tickets' => [
            'tickets.view' => 'View tickets',
            'tickets.manage' => 'Create and edit tickets',
            'workspace.use' => 'Use agent workspace',
        ],
        'Contacts' => [
            'contacts.view' => 'View contacts',
            'contacts.manage' => 'Manage contacts and organizations',
        ],
        'Assets' => [
            'assets.view' => 'View assets',
            'assets.manage' => 'Manage assets',
        ],
        'Knowledge' => [
            'knowledge.manage' => 'Manage knowledge base',
        ],
        'Reports' => [
            'reports.view' => 'View reports and dashboard',
        ],
        'Settings' => [
            'settings.team' => 'Manage team members',
            'settings.roles' => 'Manage roles and permissions',
            'settings.billing' => 'Manage billing',
            'settings.security' => 'Manage security',
            'audit.view' => 'View audit logs',
            'settings.notifications' => 'Manage notifications',
            'settings.email' => 'Manage email',
            'settings.sla' => 'Manage SLA',
            'settings.workforce' => 'Manage departments and teams',
            'settings.performance' => 'View agent performance',
            'settings.channels' => 'Manage channels',
            'settings.automation' => 'Manage automation',
            'settings.integrations' => 'Manage integrations',
            'settings.ai' => 'Manage AI',
            'settings.service-catalog' => 'Manage service catalog',
            'settings.csat' => 'Manage CSAT',
        ],
    ],

    'protected_roles' => ['admin', 'agent', 'customer'],

    'default_role_permissions' => [
        'admin' => '*',
        'agent' => [
            'access.agent',
            'tickets.view',
            'tickets.manage',
            'workspace.use',
            'contacts.view',
            'contacts.manage',
            'assets.view',
            'knowledge.manage',
            'reports.view',
        ],
        'customer' => [],
    ],
];
