<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tenants:run sla:check-breaches')->everyFiveMinutes();
Schedule::command('tenants:run channels:poll-inboxes')->everyMinute();
Schedule::command('tenants:run security:purge-retention')->daily();
Schedule::command('tenants:run reports:dispatch-scheduled')->hourly();
Schedule::command('tenants:run automation:process-scheduled')->everyMinute();
