<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Server-side auto clock-out safety net: closes shifts even when the employee's
// phone is off or the app was killed. Requires the scheduler to be running
// (cron: `* * * * * php artisan schedule:run`, or `php artisan schedule:work`).
Schedule::command('time-clock:auto-clock-out')
    ->everyMinute()
    ->withoutOverlapping();

// Daily reminder until the employee uploads a renewed document and sets a new expiry.
Schedule::command('documents:renewal-reminders')
    ->dailyAt('09:00')
    ->timezone((string) config('app.display_timezone', 'Australia/Brisbane'))
    ->withoutOverlapping();
