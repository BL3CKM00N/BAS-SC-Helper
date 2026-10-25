<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminders are nagged on a rolling schedule, so check for due ones every minute.
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();

// Safety net: an open team that nobody is in any more is removed (the app also does this right away).
Schedule::command('teams:prune')->everyFiveMinutes();

// Old sign-in log and audit log entries expire (see the KEEP_DAYS constants on the models).
Schedule::command('model:prune')->daily();
