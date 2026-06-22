<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

use Illuminate\Support\Facades\Schedule;

Schedule::command('zkteco:sync-k50')->everyMinute()->withoutOverlapping();
Schedule::command('attendance:generate-daily')->dailyAt('00:05');
// Time-tracking reminders fire when the current minute matches a configured time.
Schedule::command('time-tracking:send-reminders')->everyMinute()->withoutOverlapping();

// Daily attendance report — fires once at the configurable send_time on working
// days (dynamic, no redeploy needed). Guarded against duplicate sends.
Schedule::call(function () {
    $settings = \App\Models\AttendanceReportSettings::getSettings();
    if (!$settings->is_enabled || !$settings->isWorkingDay(\Carbon\Carbon::today())) {
        return;
    }
    if (\Carbon\Carbon::now()->format('H:i') !== \Carbon\Carbon::parse($settings->send_time)->format('H:i')) {
        return;
    }
    if (\App\Models\AttendanceReportLog::forDate(\Carbon\Carbon::today())->where('triggered_by', 'scheduled')->exists()) {
        return;
    }
    app(\App\Services\AttendanceReportService::class)->sendDailyReport(\Carbon\Carbon::today());
})->everyMinute()->name('daily-attendance-report')->withoutOverlapping();
