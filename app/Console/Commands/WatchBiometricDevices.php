<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Models\ZktecoDevice;
use App\Notifications\BiometricDeviceSilent;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Notice when a biometric device stops reaching the server, and say so.
 *
 * Everything needed to see this was already recorded; what was missing was
 * anybody looking. A device polls every few seconds, so silence for an hour is
 * already abnormal — but on 22 October one went quiet mid-morning and the
 * first anybody knew was the next day, because the only way to find out was to
 * open a page and read it.
 *
 * Reports an outage once rather than every hour it continues, and says so
 * again when it comes back, since "is it fixed" is the next question.
 */
class WatchBiometricDevices extends Command
{
    protected $signature = 'zkteco:watch-devices
                            {--hours=2 : Silence this long counts as an outage}
                            {--dry : Report only, send nothing}';

    protected $description = 'Tell HR when a biometric device stops reporting, and when it returns.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dry = (bool) $this->option('dry');

        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $sent = 0;

        $run = function () use ($hours, $dry, &$sent) {
            $cutoff = now()->subHours($hours);

            // Only devices that have ever reached us. One that never has is
            // either not set up yet or decommissioned, and neither is an
            // outage worth waking somebody for.
            $devices = ZktecoDevice::where('is_active', true)
                ->whereNotNull('last_contact_at')
                ->get();

            foreach ($devices as $device) {
                $silent = $device->last_contact_at->lt($cutoff);

                if ($silent) {
                    $this->flagSilent($device, $dry, $sent);
                    continue;
                }

                $this->flagBack($device, $dry, $sent);
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

        $this->info(($dry ? 'Would send ' : 'Sent ') . $sent . ' notification(s).');

        return self::SUCCESS;
    }

    private function flagSilent(ZktecoDevice $device, bool $dry, int &$sent): void
    {
        $minutes = (int) $device->last_contact_at->diffInMinutes(now());

        // Remember when it started, so the message says how long rather than
        // how long since we happened to check.
        if (! $device->silent_since && ! $dry) {
            $device->forceFill(['silent_since' => $device->last_contact_at])->saveQuietly();
        }

        if ($device->silence_notified_at) {
            $this->line("   {$device->name}: still quiet ({$minutes} min) — already reported");

            return;
        }

        $this->line("   {$device->name}: quiet for {$minutes} min — reporting");

        if ($dry) {
            $sent += $this->recipients()->count();

            return;
        }

        foreach ($this->recipients() as $admin) {
            try {
                $admin->notify(new BiometricDeviceSilent($device, false, $minutes));
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $device->forceFill(['silence_notified_at' => now()])->saveQuietly();
    }

    private function flagBack(ZktecoDevice $device, bool $dry, int &$sent): void
    {
        // Nothing to say unless it was reported missing.
        if (! $device->silence_notified_at) {
            if ($device->silent_since && ! $dry) {
                $device->forceFill(['silent_since' => null])->saveQuietly();
            }

            return;
        }

        $this->line("   {$device->name}: back — reporting recovery");

        if ($dry) {
            $sent += $this->recipients()->count();

            return;
        }

        foreach ($this->recipients() as $admin) {
            try {
                $admin->notify(new BiometricDeviceSilent($device, true));
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $device->forceFill(['silent_since' => null, 'silence_notified_at' => null])->saveQuietly();
    }

    private function recipients()
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->where('account_status', '!=', 'deactivated')
            ->get();
    }
}
