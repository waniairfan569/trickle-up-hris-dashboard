<?php

namespace App\Console\Commands;

use App\Models\AttendanceCorrection;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * One-time repair: attendance corrections used to store the employee's typed
 * wall-clock time labeled with the SERVER timezone instead of the employee's,
 * shifting every correction by the UTC offset (e.g. 9:27 AM shown as 2:27 PM).
 *
 * This relabels each stored correction time: take its wall-clock value as it
 * sits in the canonical timezone, stamp it with the employee's timezone, and
 * convert back to canonical. Where the two timezones are equal it's a no-op —
 * safe to run anywhere. Approved corrections whose attendance record still
 * carries the shifted times get the record fixed + recalculated as well.
 */
class FixCorrectionTimes extends Command
{
    protected $signature = 'attendance:fix-correction-times
        {--dry-run : Show what would change without writing}
        {--before= : Only fix corrections created before this time (default: now)}
        {--force : Run again even though it already ran once}';

    protected $description = 'Relabel correction times stored in the server timezone as employee-local times (one-time repair)';

    public function handle(TimezoneService $tz): int
    {
        $dry = (bool) $this->option('dry-run');

        // One-time repair: corrections submitted AFTER the code fix are stored
        // correctly — relabeling them (or re-running this) would shift good
        // data. A marker file + created-before cutoff guard against both.
        $marker = storage_path('app/fix-correction-times.done');
        if (!$dry && !$this->option('force') && is_file($marker)) {
            $this->warn('Already ran on ' . trim((string) file_get_contents($marker)) . ' — refusing to shift times twice. Use --force only if you are sure.');

            return self::FAILURE;
        }
        $cutoff = $this->option('before') ? Carbon::parse($this->option('before')) : now();

        $canonical = $tz->canonicalTimezone();
        $fixedCorrections = 0;
        $fixedRecords = 0;

        foreach (AttendanceCorrection::with(['employee', 'record'])->where('created_at', '<', $cutoff)->get() as $correction) {
            $employee = $correction->employee;
            if (!$employee) {
                continue;
            }
            $userTz = $tz->getEffectiveTimezone($employee);
            if ($userTz === $canonical) {
                continue; // naive parse was already correct — nothing to fix
            }

            $relabel = fn (?Carbon $t) => $t
                ? Carbon::parse($t->format('Y-m-d H:i:s'), $userTz)->setTimezone($canonical)
                : null;

            $oldIn = $correction->requested_clock_in;
            $oldOut = $correction->requested_clock_out;
            $newIn = $relabel($oldIn);
            $newOut = $relabel($oldOut);

            $changed = ($oldIn && !$oldIn->equalTo($newIn)) || ($oldOut && !$oldOut->equalTo($newOut));
            if (!$changed) {
                continue;
            }

            $label = $employee->first_name . ' ' . $employee->last_name . ' ' . $correction->correction_date?->format('Y-m-d');
            $this->line(($dry ? '[dry] ' : '') . "correction #{$correction->id} {$label}: "
                . ($oldIn ? $oldIn->format('H:i') . '→' . $newIn->format('H:i') : '—') . ' / '
                . ($oldOut ? $oldOut->format('H:i') . '→' . $newOut->format('H:i') : '—'));

            if (!$dry) {
                $correction->forceFill([
                    'requested_clock_in' => $newIn,
                    'requested_clock_out' => $newOut,
                ])->save();
            }
            $fixedCorrections++;

            // An approved correction wrote the shifted times into the record —
            // fix it only while it still carries exactly those shifted values.
            $record = $correction->record;
            if ($correction->status === 'approved' && $record) {
                $inMatches = (!$oldIn && !$record->clock_in) || ($oldIn && $record->clock_in && $record->clock_in->equalTo($oldIn));
                $outMatches = (!$oldOut && !$record->clock_out) || ($oldOut && $record->clock_out && $record->clock_out->equalTo($oldOut));
                if ($inMatches && $outMatches && ($oldIn || $oldOut)) {
                    $this->line(($dry ? '[dry] ' : '') . "  └ attendance record #{$record->id} re-timed + recalculated");
                    if (!$dry) {
                        $record->clock_in = $newIn;
                        $record->clock_out = $newOut;
                        $record->recalculate();
                        $record->save();
                    }
                    $fixedRecords++;
                }
            }
        }

        $this->info(($dry ? '[dry-run] would fix ' : 'Fixed ') . "{$fixedCorrections} correction(s), {$fixedRecords} attendance record(s).");

        if (!$dry) {
            @file_put_contents($marker, now()->toDateTimeString());
        }

        return self::SUCCESS;
    }
}
