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

// Daily nudge to HR/admins about probation reviews that are overdue (period
// ended, not yet confirmed/failed). Completion notices fire on confirmation.
Schedule::command('probation:review-reminders')->dailyAt('07:00')->withoutOverlapping();

// Leave-year renewals (encashment + carry forward + fresh balances) — runs any
// setting whose next_renewal_date is due.
Schedule::command('leave:process-renewals')->dailyAt('01:00')->withoutOverlapping()->runInBackground();
Schedule::command('forms:open-monthly')->dailyAt('02:00')->withoutOverlapping();

// Daily attendance report — fires once at the configurable send_time on working
// days (dynamic, no redeploy needed). Guarded against duplicate sends.
Schedule::call(function () {
    $settings = \App\Models\AttendanceReportSettings::getSettings();
    // Interpret send_time in the report's own timezone (default UK), so it lands
    // at the configured local hour no matter the server's timezone (UTC in prod).
    $tz = $settings->effectiveTimezone();
    $today = \Carbon\Carbon::today($tz);
    if (!$settings->is_enabled || !$settings->isWorkingDay($today)) {
        return;
    }
    if (\Carbon\Carbon::now($tz)->format('H:i') !== \Carbon\Carbon::parse($settings->send_time)->format('H:i')) {
        return;
    }
    if (\App\Models\AttendanceReportLog::forDate($today)->where('triggered_by', 'scheduled')->exists()) {
        return;
    }
    app(\App\Services\AttendanceReportService::class)->sendDailyReport($today);
})->everyMinute()->name('daily-attendance-report')->withoutOverlapping();

// Redact one-time login codes a week after they were sent (privacy / least retention).
Schedule::command('code-requests:purge-codes')->dailyAt('03:30')->withoutOverlapping();

// SaaS trial lifecycle — remind before expiry, wall expired workspaces, and
// hard-suspend once the post-trial grace period has elapsed.
Schedule::command('subscriptions:check-trials')->dailyAt('02:30')->withoutOverlapping();

// Daily off-site backup — database (+ uploaded files), pruned by retention.
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping()->runInBackground();

// Configurable daily admin reminders (WFH tomorrow / late today) — fire at the
// time each workspace's super-admin set; each sends once a day.
Schedule::command('reminders:admin-daily')->everyMinute()->withoutOverlapping();

// Scheduler heartbeat — proves the cron is actually running, surfaced by /health.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put(
    \App\Http\Controllers\HealthController::HEARTBEAT_KEY, now()->toIso8601String(), 900
))->everyMinute()->name('scheduler-heartbeat');
