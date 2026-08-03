<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

use Illuminate\Support\Facades\Schedule;

Schedule::command('zkteco:sync-k50')->everyMinute()->withoutOverlapping();
Schedule::command('attendance:generate-daily')->dailyAt('00:05');
// Recompute lateness leave deductions each morning (catch-all for corrections).
Schedule::command('attendance:sync-lateness')->dailyAt('00:20');
// Time-tracking reminders fire when the current minute matches a configured time.
// Employees are emailed (+ in-app) at their shift clock-in / clock-out times.
Schedule::command('time-tracking:send-reminders')->everyMinute()->withoutOverlapping();

// Super-admin daily recap of who clocked in / out (one email per day).
Schedule::command('attendance:admin-clock-summary')->dailyAt('19:00');

// Email all employees the day before about events happening tomorrow.
Schedule::command('events:send-reminders')->dailyAt('18:00');

// Remind the current signer when an assigned document has gone unsigned for 24+ hours.
Schedule::command('documents:send-signature-reminders')->hourly()->withoutOverlapping();

// Email the employee + admins when a probation period completes (end date reached).
Schedule::command('probation:notify-completions')->dailyAt('07:00')->withoutOverlapping();

// Leave-year renewals (encashment + carry forward + fresh balances) — runs any
// setting whose next_renewal_date is due.
Schedule::command('leave:process-renewals')->dailyAt('01:00')->withoutOverlapping()->runInBackground();
Schedule::command('forms:open-monthly')->dailyAt('02:00')->withoutOverlapping();

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
