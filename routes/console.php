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
