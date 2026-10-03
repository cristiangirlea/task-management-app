<?php

use Illuminate\Support\Facades\Schedule;

// The lock expires before the next hourly run, so a run killed mid-way
// (a deploy) does not block the reconcile for the default 24 hours.
$reconcile = Schedule::command('billing:reconcile-seats')->hourly()->withoutOverlapping(55);

if ($output = config('logging.schedule_output')) {
    $reconcile->appendOutputTo($output);
}

// Proves the scheduler is running tasks; the container's health check reads it.
Schedule::call(fn () => touch(storage_path('framework/schedule-heartbeat')))
    ->everyMinute()
    ->name('heartbeat');

// Expired and revoked OAuth tokens and codes (MCP clients), kept a week.
Schedule::command('passport:purge')->daily()->withoutOverlapping();
