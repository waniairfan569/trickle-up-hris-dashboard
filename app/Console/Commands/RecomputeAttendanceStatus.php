<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Re-derives late / early-departure / overtime / present for records that have a
 * clock-in AND clock-out, using the corrected shift- and timezone-aware expected
 * start/end. Fixes rows mislabelled "early departure" (etc.) when shift times were
 * parsed in the wrong timezone. Leave/absent/holiday/no-clock-out rows are left alone.
 *
 *   php artisan attendance:recompute-status --all              # fix everything
 *   php artisan attendance:recompute-status --all --dry-run    # preview everything
 *   php artisan attendance:recompute-status --date=2026-08-07
 *   php artisan attendance:recompute-status --from=2026-08-01 --to=2026-08-07
 */
class RecomputeAttendanceStatus extends Command
{
    protected $signature = 'attendance:recompute-status {--all : Every record (ignores date filters)} {--date= : Single date (Y-m-d)} {--from=} {--to=} {--dry-run}';

    protected $description = 'Re-evaluate late/early-departure/overtime status for existing attendance records (shift + timezone aware).';

    public function handle(): int
    {
        $all  = (bool) $this->option('all');
        $from = $this->option('from') ?: $this->option('date') ?: Carbon::today()->toDateString();
        $to   = $this->option('to')   ?: $this->option('date') ?: $from;
        $dry  = (bool) $this->option('dry-run');

        $settings = AttendanceSetting::first() ?? new AttendanceSetting();
        $earlyThr = (int) ($settings->early_departure_threshold_minutes ?? 0);
        $otThr    = (int) ($settings->overtime_threshold_minutes ?? 0);

        $query = AttendanceRecord::with('employee')
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->whereIn('status', ['present', 'late', 'early_departure', 'overtime']);

        if (!$all) {
            $query->whereBetween('date', [$from, $to]);
        }

        $scope = $all ? 'ALL dates' : "{$from} → {$to}";
        $this->info(($dry ? '[dry-run] ' : '') . "Recomputing status for {$scope}…");

        $tz = app(\App\Services\TimezoneService::class);
        $scanned = 0;
        $changed = 0;

        $query->orderBy('id')->chunkById(500, function ($records) use (&$scanned, &$changed, $tz, $earlyThr, $otThr, $dry) {
            foreach ($records as $r) {
                $scanned++;
                if (!$r->employee) {
                    continue;
                }

                // --- LATE: clock-in vs the assigned shift's start + grace (employee tz) ---
                $localIn = $tz->toUserTime($r->clock_in->copy(), $r->employee);
                $cutoff  = AttendanceRecord::lateCutoffFor($r->employee, $localIn);
                $isLate  = $localIn->greaterThanOrEqualTo($cutoff);
                $lateMin = $isLate ? (int) max(1, round($cutoff->diffInMinutes($localIn))) : 0;
                $newStatus = $isLate ? 'late' : 'present';

                // --- OVERTIME / EARLY DEPARTURE: clock-out vs the assigned shift's end ---
                $expectedEnd = AttendanceRecord::expectedEndDateTimeFor($r->employee, $r->date->copy());
                $ot = 0;
                $early = 0;
                if ($expectedEnd) {
                    if ($r->clock_out->greaterThan($expectedEnd->copy()->addMinutes($otThr))) {
                        $newStatus = 'overtime';
                        $ot = (int) $expectedEnd->diffInMinutes($r->clock_out);
                    } elseif ($r->clock_out->lessThan($expectedEnd->copy()->subMinutes($earlyThr))) {
                        $newStatus = 'early_departure';
                        $early = (int) $r->clock_out->diffInMinutes($expectedEnd);
                    }
                }

                // --- Approved partial-day leave wins over late / early-departure ---
                $partial = AttendanceRecord::partialDayLeaveFor($r->user_id, $r->date->format('Y-m-d'));
                if ($partial === 'half_day') {
                    $newStatus = 'half_day';
                    $lateMin = 0;
                    $ot = 0;
                    $early = 0;
                } elseif ($partial === 'hourly' && $newStatus === 'late') {
                    $newStatus = 'present';
                    $lateMin = 0;
                }

                if ($newStatus !== $r->status
                    || (int) $r->late_minutes !== $lateMin
                    || (int) $r->overtime_minutes !== $ot
                    || (int) $r->early_departure_minutes !== $early) {
                    $this->line(sprintf('  %s  %-24s  %s → %s', $r->date->toDateString(), \Illuminate\Support\Str::limit(optional($r->employee)->full_name ?? '—', 24), $r->status, $newStatus));
                    if (!$dry) {
                        $r->status = $newStatus;
                        $r->late_minutes = $lateMin;
                        $r->overtime_minutes = $ot;
                        $r->early_departure_minutes = $early;
                        $r->save();
                    }
                    $changed++;
                }
            }
        });

        $this->info(($dry ? '[dry-run] ' : '') . "Done — scanned {$scanned} record(s), {$changed} " . ($dry ? 'would change.' : 'updated.'));

        return self::SUCCESS;
    }
}
