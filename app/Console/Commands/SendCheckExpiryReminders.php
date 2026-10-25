<?php

namespace App\Console\Commands;

use App\Models\EmployeeCheck;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EmployeeChecksDue;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Chase checks that have lapsed, or are about to.
 *
 * Recording a check is the easy half. The half that matters is noticing when
 * it stops counting — a visa expires and the right-to-work check expires with
 * it, and nobody finds out by looking at a list they have no reason to open.
 */
class SendCheckExpiryReminders extends Command
{
    protected $signature = 'checks:send-expiry-reminders
                            {--days=30 : How far ahead to warn}
                            {--dry : Report only, send nothing}';

    protected $description = 'Tell HR about pre-employment checks that have lapsed or are about to.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry');

        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $sent = 0;

        $run = function () use ($days, $dry, &$sent) {
            $expired = EmployeeCheck::with('employee')
                ->where('status', 'passed')
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', today())
                ->orderBy('expires_on')->get();

            $expiring = EmployeeCheck::with('employee')
                ->where('status', 'passed')
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '>=', today())
                ->whereDate('expires_on', '<=', today()->addDays($days))
                ->orderBy('expires_on')->get();

            // Somebody who has already left is not working without a check.
            $expired = $expired->filter(fn ($c) => $this->stillHere($c));
            $expiring = $expiring->filter(fn ($c) => $this->stillHere($c));

            if ($expired->isEmpty() && $expiring->isEmpty()) {
                return;
            }

            $this->line('  ' . $expired->count() . ' lapsed, ' . $expiring->count() . " lapsing within {$days} days");

            foreach ($expired->take(10) as $c) {
                $this->line("    expired  {$c->employee?->full_name}: {$c->typeLabel()} ({$c->expires_on->format('j M Y')})");
            }

            if ($dry) {
                return;
            }

            foreach ($this->recipients() as $admin) {
                try {
                    $admin->notify(new EmployeeChecksDue($expired, $expiring, $days));
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        };

        if ($tenants->count() <= 1) {
            $manager->set($tenants->first());
            $run();
        } else {
            foreach ($tenants as $tenant) {
                $manager->set($tenant);
                $run();
            }
        }

        $manager->set(null);

        $this->info(($dry ? 'Would notify ' : 'Notified ') . $sent . ' administrator(s).');

        return self::SUCCESS;
    }

    /** A check on somebody who has left is history, not a problem. */
    private function stillHere(EmployeeCheck $check): bool
    {
        $employee = $check->employee;

        if (! $employee || $employee->account_status === 'deactivated') {
            return false;
        }

        return ! \App\Models\Employee::where('user_id', $employee->id)
            ->whereNotNull('termination_date')
            ->whereDate('termination_date', '<=', today())
            ->exists();
    }

    private function recipients()
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->where('account_status', '!=', 'deactivated')
            ->get();
    }
}
