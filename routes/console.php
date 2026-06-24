<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tenants:run sla:check-breaches')->everyFiveMinutes();
Schedule::command('tenants:run channels:poll-inboxes')->everyMinute();
Schedule::command('tenants:run security:purge-retention')->daily();
Schedule::command('tenants:run reports:dispatch-scheduled')->hourly();
Schedule::command('tenants:run automation:process-scheduled')->everyMinute();
Schedule::command('tenants:run tickets:unsnooze')->everyMinute();
Schedule::command('billing:enforce-grace')->hourly()->withoutOverlapping();
Schedule::command('tenants:purge-expired')->daily()->withoutOverlapping();
Schedule::command('registrations:purge-expired')->everySixHours()->withoutOverlapping();
Schedule::command('platform:run-backups')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => app(\App\Domains\Platform\Services\PlatformBackupScheduleService::class)->isDue());
Schedule::command('platform:check-tenant-infrastructure')->hourly()->withoutOverlapping();
Schedule::command('platform:run-tenant-infrastructure-backups')->everyMinute()->withoutOverlapping();
Schedule::command('platform:check-pending-tenant-migrations')->daily()->withoutOverlapping();
Schedule::command('platform:send-trial-nurture')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('platform:send-subscription-ending-reminders')->dailyAt('09:15')->withoutOverlapping();
Schedule::command('telescope:prune')->daily();
Schedule::command('logs:clear')->dailyAt('03:00')->withoutOverlapping();
