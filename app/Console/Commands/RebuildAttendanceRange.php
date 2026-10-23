<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use App\Models\ZktecoRawPunch;
use App\Services\TimezoneService;
use App\Services\ZktecoK50Service;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild biometric attendance for a few days, rather than for all time.
 *
 * The button on the ZKTeco page deletes every attendance row that came from a
 * device and re-pairs every punch ever recorded. That is the right tool after
 * a device-wide mistake and the wrong one when today looks wrong: it rewrites
 * months of history to fix an afternoon.
 *
 * This does the same work inside a date range. A punch belongs to the day it
 * happened in the employee's own timezone, not the server's, so the window is
 * widened when selecting punches and each one is then checked against the
 * range by its local date.
 */
class RebuildAttendanceRange extends Command
{
    protected $signature = 'attendance:rebuild
                            {--from= : First day to rebuild (default: today)}
                            {--to= : Last day to rebuild (default: same as --from)}
                            {--user= : Limit to one employee (email, employee id or user id)}
                            {--include-edited : Also rebuild days somebody corrected by hand}
                            {--dry : Show what would happen, change nothing}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Re-pair biometric punches into attendance for a date range.';

    public function handle(ZktecoK50Service $service): int
    {
        $dry = (bool) $this->option('dry');

        $from = Carbon::parse($this->option('from') ?: 'today')->startOfDay();
        $to = Carbon::parse($this->option('to') ?: $from)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $user = null;
        if ($this->option('user')) {
            $user = $this->findUser((string) $this->option('user'));
            if (! $user) {
                $this->error('No employee matches "' . $this->option('user') . '".');

                return self::FAILURE;
            }
        }

        $days = $from->diffInDays($to) + 1;
        $this->line('');
        $this->info('  Rebuilding ' . $from->toDateString() . ' to ' . $to->toDateString()
            . " ({$days} day" . ($days === 1 ? '' : 's') . ')'
            . ($user ? " for {$user->full_name}" : ' for everybody')
            . ($dry ? '  [dry run]' : ''));

        // Punches are stored in the canonical timezone; a day in somebody's own
        // timezone can reach a day either side of that, so cast wider and filter.
        $window = [
            $from->copy()->subDay()->startOfDay(),
            $to->copy()->addDay()->endOfDay(),
        ];

        $punches = ZktecoRawPunch::withoutGlobalScopes()
            ->whereNotNull('user_id')
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('punched_at', $window)
            ->with('employee')
            ->orderBy('punched_at')
            ->get();

        $tz = app(TimezoneService::class);

        // Keep only punches whose own local day is inside the range.
        $inRange = $punches->filter(function ($punch) use ($tz, $from, $to) {
            if (! $punch->employee) {
                return false;
            }
            $local = $tz->toUserTime($punch->punched_at, $punch->employee)->toDateString();

            return $local >= $from->toDateString() && $local <= $to->toDateString();
        })->values();

        $this->line('  ' . $inRange->count() . ' punch(es) fall in that range'
            . ' (' . $punches->count() . ' looked at)');

        $existing = AttendanceRecord::withoutGlobalScopes()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $fromDevice = $existing->where('source', 'zkteco');
        $edited = $fromDevice->whereNotNull('edited_by');
        $manual = $existing->where('source', '!=', 'zkteco');

        $this->line('  ' . $existing->count() . ' attendance row(s) in range: '
            . $fromDevice->count() . ' from the device, ' . $manual->count() . ' from elsewhere');

        if ($manual->isNotEmpty()) {
            $this->line('    rows not from the device are left alone');
        }

        $toClear = $this->option('include-edited') ? $fromDevice : $fromDevice->whereNull('edited_by');

        if ($edited->isNotEmpty()) {
            $this->option('include-edited')
                ? $this->warn("    {$edited->count()} hand-corrected row(s) WILL be overwritten")
                : $this->line("    {$edited->count()} hand-corrected row(s) kept (pass --include-edited to redo them)");
        }

        if ($dry) {
            $this->line('');
            $this->info('  Would clear ' . $toClear->count() . ' row(s) and re-pair '
                . $inRange->count() . ' punch(es). Nothing was changed.');
            $this->line('');

            return self::SUCCESS;
        }

        if ($inRange->isEmpty() && $toClear->isEmpty()) {
            $this->line('');
            $this->warn('  Nothing to do for that range.');
            $this->line('');

            return self::SUCCESS;
        }

        // Say plainly what is about to be destroyed, and make somebody agree to
        // it. This runs on live data, and the range is the only thing standing
        // between "fix today" and "rewrite the year".
        $outside = AttendanceRecord::withoutGlobalScopes()
            ->where(function ($q) use ($from, $to) {
                $q->where('date', '<', $from->toDateString())
                    ->orWhere('date', '>', $to->toDateString());
            })->count();

        $this->line('');
        $this->warn('  About to delete ' . $toClear->count() . ' attendance row(s), only inside '
            . $from->toDateString() . '–' . $to->toDateString() . '.');
        $this->line("  {$outside} row(s) outside that range will not be read or written.");

        if (! $this->option('force') && ! $this->confirm('  Go ahead?', false)) {
            $this->line('  Nothing was changed.');
            $this->line('');

            return self::SUCCESS;
        }

        $protectedIds = $toClear->isEmpty() ? [] : $toClear->pluck('id')->all();

        $processed = DB::transaction(function () use ($service, $inRange, $protectedIds) {
            if ($protectedIds) {
                BreakRecord::whereIn('attendance_record_id', $protectedIds)->delete();
                AttendanceRecord::withoutGlobalScopes()->whereIn('id', $protectedIds)->forceDelete();
            }

            ZktecoRawPunch::withoutGlobalScopes()
                ->whereIn('id', $inRange->pluck('id'))
                ->update(['is_processed' => false, 'processed_at' => null]);

            $done = 0;
            foreach ($inRange as $punch) {
                // A kept row (hand-corrected) must not be re-paired into.
                try {
                    $service->processPunch($punch, $punch->employee);
                    $done++;
                } catch (\Throwable $e) {
                    report($e);
                    $this->error('  punch #' . $punch->id . ': ' . $e->getMessage());
                }
            }

            return $done;
        });

        $after = AttendanceRecord::withoutGlobalScopes()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $this->line('');
        $this->info("  Re-paired {$processed} punch(es). {$after} attendance row(s) now in that range.");
        $this->line('  Everything outside ' . $from->toDateString() . '–' . $to->toDateString() . ' was not touched.');
        $this->line('');

        return self::SUCCESS;
    }

    private function findUser(string $needle): ?User
    {
        return (is_numeric($needle) ? User::withoutGlobalScopes()->where('id', $needle)->first() : null)
            ?: User::withoutGlobalScopes()->where('email', $needle)->first()
            ?: User::withoutGlobalScopes()->where('employee_id', $needle)->first()
            ?: User::withoutGlobalScopes()->where('zkteco_uid', $needle)->first();
    }
}
