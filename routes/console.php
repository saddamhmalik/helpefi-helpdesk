<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sla:check-breaches')->everyFiveMinutes();
Schedule::command('channels:poll-inboxes')->everyMinute();
Schedule::command('security:purge-retention')->daily();
Schedule::command('reports:dispatch-scheduled')->hourly();
Schedule::command('automation:process-scheduled')->everyMinute();
