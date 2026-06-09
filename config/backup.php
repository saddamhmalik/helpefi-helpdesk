<?php

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'path' => 'backups',
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'schedule_enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', false),
    'schedule_frequency' => env('BACKUP_SCHEDULE_FREQUENCY', 'daily'),
    'schedule_weekday' => (int) env('BACKUP_SCHEDULE_WEEKDAY', 1),
    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),
];
