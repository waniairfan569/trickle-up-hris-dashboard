<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\TimeOffRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Undoes "On Leave" attendance days that were stamped by an approved Work From
 * Home request. WFH is not time off — the employee is working remotely and still
 * clocks in — so those days must not read as leave. Approving a WFH request no
 * longer marks attendance at all; this cleans up the days already stamped.
 *
 * Only rows with NO clock-in are touched (a day they actually worked was never
 * overwritten in the first place):
 *   - past days   -> "absent" (they didn't clock in; the normal rule applies)
 *   - today / future -> the row is removed, so they can still clock in normally
 */
class FixWfhAttendanceDays extends Command
{
    protected $signature = 'attendance:fix-wfh-days
                            {--user= : Limit to one user id}
                            {--dry-run : Show what would change without saving}';

    protected $description = 'Clear "On Leave" attendance days that came from a Work From Home request.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $requests = TimeOffRequest::where('status', 'approved')
            ->workFromHomeOnly()
            ->when($this->option('user'), fn ($q) => $q->where('user_id', $this->option('user')))
            ->get(['id', 'user_id', 'start_date', 'end_date']);

        $today = Carbon::today();
        $cleared = 0;
        $removed = 0;

        foreach ($requests as $req) {
            $end = Carbon::parse($req->end_date)->startOfDay();
            for ($d = Carbon::parse($req->start_date)->startOfDay(); $d->lte($end); $d->addDay()) {
                $record = AttendanceRecord::where('user_id', $req->user_id)
                    ->whereDate('date', $d->toDateString())
                    ->where('status', 'on_leave')
                    ->whereNull('clock_in')
                    ->first();

                if (!$record) {
                    continue;
                }

                if ($d->lt($today)) {
                    $this->line(($dry ? '[dry] ' : '') . "user {$req->user_id} {$d->toDateString()}: on_leave -> absent");
                    if (!$dry) {
                        $record->status = 'absent';
                        $record->save();
                    }
                    $cleared++;
                } else {
                    $this->line(($dry ? '[dry] ' : '') . "user {$req->user_id} {$d->toDateString()}: on_leave row removed (can clock in)");
                    if (!$dry) {
                        $record->delete();
                    }
                    $removed++;
                }
            }
        }

        $this->info(($dry ? 'Dry run: ' : '')
            . "{$cleared} past WFH day(s) corrected to absent, {$removed} current/future WFH day(s) freed up.");

        return self::SUCCESS;
    }
}
