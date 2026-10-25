<?php

namespace App\Console\Commands;

use App\Models\ShiftCoverageAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ShiftsNeedCover;
use App\Services\ShiftCoverageService;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Say when the rota has holes in it, before the morning they open.
 *
 * Approving leave for somebody on a shift empties that shift, and nothing
 * joined those two facts up: the rota still showed them on it and the leave
 * book still showed them away. Both were right; nobody was looking at them
 * together.
 *
 * Reports each hole once. Fill it and the note is cleared, so if the cover
 * later cancels the same hole is reported again — silence has to mean "no
 * change", never "nobody is checking".
 */
class WatchShiftCoverage extends Command
{
    protected $signature = 'rota:watch-coverage
                            {--days=14 : How far ahead to look}
                            {--dry : Report only, send nothing}';

    protected $description = 'Tell HR which rostered shifts have nobody on them because the person is on leave.';

    public function handle(ShiftCoverageService $coverage): int
    {
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry');

        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $sent = 0;

        $run = function () use ($coverage, $days, $dry, &$sent) {
            $from = today();
            $to = today()->copy()->addDays($days);

            $gaps = $coverage->gaps($from, $to);
            $open = $gaps->filter(fn ($g) => ! $g->cover);

            // Holes that have closed since they were reported: clear the note
            // so a cover that falls through is reported again.
            $openKeys = $open->map(fn ($g) => ShiftCoverageAlert::keyFor(
                $g->user->id, $g->shift->id, $g->date->toDateString()
            ))->all();

            $known = ShiftCoverageAlert::whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->get();

            foreach ($known as $alert) {
                $key = ShiftCoverageAlert::keyFor($alert->user_id, $alert->shift_id, $alert->date->toDateString());

                if (! in_array($key, $openKeys, true) && ! $dry) {
                    $alert->delete();
                }
            }

            $alreadyTold = $known->map(fn ($a) => ShiftCoverageAlert::keyFor(
                $a->user_id, $a->shift_id, $a->date->toDateString()
            ))->all();

            $fresh = $open->reject(fn ($g) => in_array(
                ShiftCoverageAlert::keyFor($g->user->id, $g->shift->id, $g->date->toDateString()),
                $alreadyTold,
                true
            ))->values();

            $this->line(sprintf(
                '  %d gap(s) in the next %d days · %d uncovered · %d not yet reported',
                $gaps->count(), $days, $open->count(), $fresh->count()
            ));

            if ($fresh->isEmpty()) {
                return;
            }

            if ($dry) {
                foreach ($fresh as $gap) {
                    $this->line('   would report: ' . $gap->date->format('D j M') . ' · '
                        . $gap->shift->name . ' · ' . ($gap->user->first_name ?? '') . ' (' . $gap->reason . ')');
                }

                return;
            }

            $recipients = $this->recipients();

            if ($recipients->isEmpty()) {
                $this->warn('  No admin to tell.');

                return;
            }

            foreach ($recipients as $admin) {
                $admin->notify(new ShiftsNeedCover($fresh));
                $sent++;
            }

            foreach ($fresh as $gap) {
                ShiftCoverageAlert::updateOrCreate(
                    [
                        'user_id' => $gap->user->id,
                        'shift_id' => $gap->shift->id,
                        'date' => $gap->date->toDateString(),
                    ],
                    ['notified_at' => now()]
                );
            }
        };

        if ($tenants->count() <= 1) {
            $manager->set($tenants->first());
            $run();
        } else {
            foreach ($tenants as $tenant) {
                $manager->set($tenant);
                $this->line($tenant->name ?? ('Tenant ' . $tenant->id));
                $run();
            }
        }

        $manager->set(null);

        $this->info(($dry ? 'Would send ' : 'Sent ') . $sent . ' notification(s).');

        return self::SUCCESS;
    }

    private function recipients()
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->where('account_status', '!=', 'deactivated')
            ->get();
    }
}
