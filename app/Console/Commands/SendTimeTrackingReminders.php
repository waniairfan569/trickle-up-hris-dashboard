<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\TimeTrackingPolicy;
use App\Notifications\TimeTrackingReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTimeTrackingReminders extends Command
{
    protected $signature = 'time-tracking:send-reminders {--at= : Override "now" as H:i for testing} {--dry : Report only, do not send}';

    protected $description = 'Send time-tracking reminders due at the current minute (work-schedule start/end or custom times).';

    public function handle(): int
    {
        $now = $this->option('at')
            ? Carbon::createFromFormat('H:i', $this->option('at'))
            : now();
        $nowHm = $now->format('H:i');
        $today = $now->format('D'); // Mon, Tue, …
        $dry = (bool) $this->option('dry');

        $sent = 0;
        $policies = TimeTrackingPolicy::with(['entities', 'departments'])->get();

        foreach ($policies as $policy) {
            $employees = $policy->scopedEmployeesQuery()
                ->where('exclude_from_attendance', false)
                ->with('workSchedule')->get();

            if ($policy->reminders_frequency === 'custom') {
                foreach (($policy->custom_reminder_times ?? []) as $r) {
                    if (($r['day'] ?? null) === $today && $this->hm($r['time'] ?? null) === $nowHm) {
                        foreach ($employees as $e) {
                            $sent += $this->send($e, $policy, 'Reminder to log your work time.', $dry);
                        }
                    }
                }
            } else {
                // Based on each employee's shift: nudge 5 min before clock-in/out,
                // and chase 5 min after if they still haven't clocked in/out.
                foreach ($employees as $e) {
                    $ws = $e->workSchedule;
                    if (!$ws || !$ws->isWorkingDay($now)) {
                        continue;
                    }

                    $start = $this->hm($ws->start_time);
                    $end = $this->hm($ws->end_time);

                    if ($nowHm === $this->shift($ws->start_time, -5)) {
                        $sent += $this->send($e, $policy, "Your shift starts at {$start}. Please clock in.", $dry);
                    } elseif ($nowHm === $this->shift($ws->start_time, 5)) {
                        if (!$this->hasClockedIn($e, $now)) {
                            $sent += $this->send($e, $policy, "Reminder: you haven't clocked in yet for your {$start} shift.", $dry);
                        }
                    } elseif ($nowHm === $this->shift($ws->end_time, -5)) {
                        $sent += $this->send($e, $policy, "Your shift ends at {$end}. Remember to clock out.", $dry);
                    } elseif ($nowHm === $this->shift($ws->end_time, 5)) {
                        if ($this->hasClockedIn($e, $now) && !$this->hasClockedOut($e, $now)) {
                            $sent += $this->send($e, $policy, "Reminder: you haven't clocked out yet from your {$end} shift.", $dry);
                        }
                    }
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Time-tracking reminders due at {$nowHm}: {$sent}.");

        return self::SUCCESS;
    }

    private function send($employee, TimeTrackingPolicy $policy, string $body, bool $dry): int
    {
        if (!$dry) {
            try {
                $employee->notify(new TimeTrackingReminder($policy, $body));
            } catch (\Throwable $e) {
                // Don't let one failed email (e.g. SMTP hiccup) abort the run.
                report($e);

                return 0;
            }
        }

        return 1;
    }

    /** Normalize a time value to H:i, or null. */
    private function hm($time): ?string
    {
        if (!$time) {
            return null;
        }
        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** A time value shifted by N minutes, formatted H:i (null if unparseable). */
    private function shift($time, int $minutes): ?string
    {
        if (!$time) {
            return null;
        }
        try {
            return Carbon::parse($time)->addMinutes($minutes)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function hasClockedIn($employee, Carbon $now): bool
    {
        return AttendanceRecord::where('user_id', $employee->id)
            ->whereDate('date', $now->toDateString())
            ->whereNotNull('clock_in')
            ->exists();
    }

    private function hasClockedOut($employee, Carbon $now): bool
    {
        return AttendanceRecord::where('user_id', $employee->id)
            ->whereDate('date', $now->toDateString())
            ->whereNotNull('clock_out')
            ->exists();
    }
}
