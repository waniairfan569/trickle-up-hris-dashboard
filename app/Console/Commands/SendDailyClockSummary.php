<?php

namespace App\Console\Commands;

use App\Mail\DailyClockSummaryMail;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyClockSummary extends Command
{
    protected $signature = 'attendance:admin-clock-summary {--date= : Override date as Y-m-d} {--dry : Build but do not send}';

    protected $description = 'Email super admins a daily summary of employee clock-in / clock-out activity.';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now()->startOfDay();
        $dry = (bool) $this->option('dry');

        $employees = User::where('account_status', 'active')
            ->where('exclude_from_attendance', false)
            ->with('workSchedule')->get();
        $records = AttendanceRecord::forDate($date->copy())->get()->keyBy('user_id');

        $rows = [];
        $counts = ['scheduled' => 0, 'clocked_in' => 0, 'still_in' => 0, 'not_in' => 0];

        foreach ($employees as $e) {
            $rec = $records->get($e->id);
            $scheduledToday = $e->workSchedule && $e->workSchedule->isWorkingDay($date);

            if (!$rec && !$scheduledToday) {
                continue; // not working today and no record — skip
            }

            $clockIn = $rec && $rec->clock_in ? $rec->clock_in->format('H:i') : null;
            $clockOut = $rec && $rec->clock_out ? $rec->clock_out->format('H:i') : null;

            $rows[] = [
                'name' => $e->full_name,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'status' => $rec->status ?? 'absent',
            ];

            $counts['scheduled']++;
            if ($clockIn) {
                $counts['clocked_in']++;
                if (!$clockOut) {
                    $counts['still_in']++;
                }
            } else {
                $counts['not_in']++;
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->where('account_status', '!=', 'deactivated')
            ->whereNotNull('email')
            ->get();

        if ($dry) {
            $this->info("[dry-run] {$counts['scheduled']} scheduled, {$counts['clocked_in']} in, {$counts['not_in']} missing — would email {$admins->count()} admin(s).");

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new DailyClockSummaryMail($date->copy(), $rows, $counts, $admin));
                $sent++;
            } catch (\Throwable $ex) {
                report($ex);
            }
        }

        $this->info("Clock summary for {$date->toDateString()} sent to {$sent} admin(s).");

        return self::SUCCESS;
    }
}
