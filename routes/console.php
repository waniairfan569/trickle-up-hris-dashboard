<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

use Illuminate\Support\Facades\Schedule;

Schedule::command('zkteco:sync-k50')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('attendance:generate-daily')->dailyAt('00:05');
