<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use Illuminate\Console\Command;

/**
 * Removes invalid "Absent" attendance records dated in the future with no
 * clock-in — a day that hasn't happened yet can't be an absence. These are
 * typically leftovers from a cancelled / returned-early leave under the old
 * revert behaviour (which marked reverted leave days "absent"). Safe one-off
 * cleanup; the revert logic no longer creates them.
 */
class PurgeFutureAbsent extends Command
{
    protected $signature = 'attendance:purge-future-absent {--dry-run : Show what would be removed without deleting}';

    protected $description = 'Remove invalid future "Absent" attendance records (no clock-in, dated after today).';

    public function handle(): int
    {
        $query = AttendanceRecord::where('status', 'absent')
            ->whereNull('clock_in')
            ->whereDate('date', '>', today());

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} future absent record(s) would be removed.");

            return self::SUCCESS;
        }

        $deleted = 0;
        $query->chunkById(500, function ($records) use (&$deleted) {
            foreach ($records as $r) {
                $r->delete();
                $deleted++;
            }
        });

        $this->info("Removed {$deleted} invalid future absent record(s).");

        return self::SUCCESS;
    }
}
