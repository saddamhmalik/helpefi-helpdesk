<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tenants:run sla:check-breaches')->everyFiveMinutes();
Schedule::command('tenants:run channels:poll-inboxes')->everyMinute();
Schedule::command('tenants:run security:purge-retention')->daily();
Schedule::command('tenants:run reports:dispatch-scheduled')->hourly();
Schedule::command('tenants:run automation:process-scheduled')->everyMinute();
Schedule::command('billing:backfill-stripe')
    ->hourly()
    ->withoutOverlapping()
    ->when(fn () => (bool) config('stripe.enabled') && config('stripe.secret'));
Schedule::command('billing:enforce-grace')->hourly()->withoutOverlapping();
Schedule::command('platform:run-backups')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => app(\App\Domains\Platform\Services\PlatformBackupScheduleService::class)->isDue());
