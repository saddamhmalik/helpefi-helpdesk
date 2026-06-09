<?php

return [
    'queue' => env('PLATFORM_AUDIT_QUEUE', 'default'),
    'queue_connection' => env('PLATFORM_AUDIT_QUEUE_CONNECTION', 'central'),

    'events' => [
        'platform.auth.login' => 'Platform admin signed in',
        'platform.auth.login_failed' => 'Platform sign-in failed',
        'platform.auth.logout' => 'Platform admin signed out',
        'platform.tenant.updated' => 'Workspace updated',
        'platform.tenant.blocked' => 'Workspace blocked',
        'platform.tenant.unblocked' => 'Workspace unblocked',
        'platform.tenant.plan_changed' => 'Workspace plan changed',
        'platform.user.created' => 'Platform user created',
        'platform.user.updated' => 'Platform user updated',
        'platform.user.deleted' => 'Platform user deleted',
        'platform.role.created' => 'Platform role created',
        'platform.role.updated' => 'Platform role updated',
        'platform.role.deleted' => 'Platform role deleted',
        'platform.settings.updated' => 'Platform settings updated',
        'platform.email_template.created' => 'Email template created',
        'platform.email_template.updated' => 'Email template updated',
        'platform.email_template.deleted' => 'Email template deleted',
        'platform.backup.schedule_updated' => 'Backup schedule updated',
        'platform.backup.created' => 'Backup queued',
        'platform.backup.completed' => 'Backup completed',
        'platform.backup.failed' => 'Backup failed',
        'platform.backup.deleted' => 'Backup deleted',
    ],
];
