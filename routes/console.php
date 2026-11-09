<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('parking:schedule-inspections')->hourly()->withoutOverlapping();
Schedule::command('parking:send-expiry-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('parking:expire-reservations')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('parking:anpr-health-check')->everyMinute()->withoutOverlapping();
Schedule::command('parking:purge-anpr-images')->dailyAt('02:30')->withoutOverlapping();
