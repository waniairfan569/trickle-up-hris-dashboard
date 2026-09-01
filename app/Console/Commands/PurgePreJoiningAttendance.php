<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Removes "Absent" attendance records dated BEFORE an employee's joining date.
 * Attendance starts on the day someone joins — a day they weren't employed on
 * can't be an absence. These rows came from the nightly generator running
 * between the account being created and the person's actual start date.
 *
 * Only empty rows are touched (status 'absent', no clock-in AND no clock-out),
 * so no real attendance data can ever be lost. Days with punches, leave,
 * holidays and weekends are left exactly as they are.
 */
class PurgePreJoiningAttendance extends Command
{
    protected $signature = 'attendance:purge-pre-joining
                            {--user= : Limit to one user id}
                            {--dry-run : Show what would be removed without deleting}';

    protected $description = 'Remove "Absent" attendance records dated before the employee joined.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $users = User::when($this->option('user'), fn ($q) => $q->where('id', $this->option('user')))
            ->get(['id', 'first_name', 'last_name', 'hire_date', 'joined_at']);

        $total = 0;
        $affected = 0;

        foreach ($users as $user) {
            $start = $user->employmentStartDate();
            if (!$start) {
                continue; // No joining date on file — nothing to measure against.
            }

            $query = static::preJoiningQuery($user->id, $start);
            $count = (clone $query)->count();
            if ($count === 0) {
                continue;
            }

            $affected++;
            $total += $count;
            $this->line(sprintf(
                '%s%s — %d absent day(s) before %s',
                $dry ? '[dry] ' : '',
                trim($user->first_name . ' ' . $user->last_name),
                $count,
                $start->format('d M Y')
            ));

            if (!$dry) {
                $query->chunkById(500, fn ($records) => $records->each->delete());
            }
        }

        $this->info($dry
            ? "Dry run: {$total} pre-joining absent record(s) across {$affected} employee(s) would be removed."
            : "Removed {$total} pre-joining absent record(s) across {$affected} employee(s).");

        return self::SUCCESS;
    }

    /**
     * Empty "absent" days before an employee's joining date. Shared with the
     * per-employee "Fix status" action so both clean up on identical rules.
     */
    public static function preJoiningQuery(int $userId, \Carbon\Carbon $start)
    {
        return AttendanceRecord::where('user_id', $userId)
            ->where('status', 'absent')
            ->whereNull('clock_in')
            ->whereNull('clock_out')
            ->whereDate('date', '<', $start->toDateString());
    }
}
