<?php

namespace App\Console\Commands;

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
            $employees = $policy->scopedEmployeesQuery()->with('workSchedule')->get();

            if ($policy->reminders_frequency === 'custom') {
                foreach (($policy->custom_reminder_times ?? []) as $r) {
                    if (($r['day'] ?? null) === $today && $this->hm($r['time'] ?? null) === $nowHm) {
                        foreach ($employees as $e) {
                            $sent += $this->send($e, $policy, 'Reminder to log your work time.', $dry);
                        }
                    }
                }
            } else {
                // Based on work schedule — start and end of each employee's shift.
                foreach ($employees as $e) {
                    $ws = $e->workSchedule;
                    if (!$ws || !$ws->isWorkingDay($now)) {
                        continue;
                    }
                    if ($this->hm($ws->start_time) === $nowHm) {
                        $sent += $this->send($e, $policy, 'Time to clock in for your shift.', $dry);
                    } elseif ($this->hm($ws->end_time) === $nowHm) {
                        $sent += $this->send($e, $policy, 'Time to clock out — log your hours.', $dry);
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
            $employee->notify(new TimeTrackingReminder($policy, $body));
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
}
