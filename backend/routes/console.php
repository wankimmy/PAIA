<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule reminder notifications to run every minute
Schedule::command('reminders:send')->everyMinute();

// Schedule habit aggregation to run daily
Schedule::command('habits:aggregate')->daily();

