<?php

return [
    'groups' => [
        'Workspaces' => [
            'tenants.view' => 'View workspaces',
            'tenants.manage' => 'Manage workspaces and plans',
            'payments.view' => 'View received payments',
            'subscriptions.view' => 'View workspace subscriptions',
        ],
        'Platform' => [
            'settings.view' => 'View platform settings',
            'settings.manage' => 'Manage platform settings',
            'emails.view' => 'View email templates',
            'emails.manage' => 'Manage email templates',
            'audit.view' => 'View platform audit logs',
            'backups.view' => 'View backups',
            'backups.manage' => 'Create and delete backups',
        ],
        'Access control' => [
            'users.view' => 'View platform users',
            'users.manage' => 'Manage platform users',
            'roles.view' => 'View platform roles',
            'roles.manage' => 'Manage platform roles',
        ],
        'Account' => [
            'profile.manage' => 'Manage own profile and password',
        ],
    ],

    'protected_roles' => ['super_admin'],

    'default_role_permissions' => [
        'super_admin' => '*',
        'support' => [
            'tenants.view',
            'emails.view',
            'profile.manage',
        ],
    ],
];
