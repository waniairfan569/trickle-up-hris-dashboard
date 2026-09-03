<?php

namespace App\Console\Commands;

use App\Models\AdminReminderSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AdminDailyReminder;
use App\Services\AdminReminders;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Fires the configured daily admin reminders (WFH tomorrow / late today) at the
 * time each workspace's super-admin set. Runs every minute; each reminder sends
 * once per day (guarded by a last-sent date).
 */
class SendAdminReminders extends Command
{
    protected $signature = 'reminders:admin-daily {--force : Send now, ignoring enabled/time/once-a-day guards}';

    protected $description = 'Send configured daily admin reminders (WFH tomorrow / late arrivals).';

    public function handle(AdminReminders $reminders): int
    {
        $force = (bool) $this->option('force');
        $manager = app(TenantManager::class);
        $sent = 0;

        $tenants = Tenant::all();

        // Single/pre-SaaS install (data may have a NULL tenant_id): run once with
        // no scope so all users are seen. Multi-tenant: scope to each workspace.
        if ($tenants->count() <= 1) {
            $manager->set(null);
            $sent += $this->processWorkspace($reminders, $force);
        } else {
            foreach ($tenants as $tenant) {
                $manager->set($tenant);
                $sent += $this->processWorkspace($reminders, $force);
            }
        }

        $manager->set(null);
        $this->info("Admin reminders: {$sent} notification group(s) sent.");

        return self::SUCCESS;
    }

    private function processWorkspace(AdminReminders $reminders, bool $force): int
    {
        $s = AdminReminderSetting::getSettings();
        $nowHm = now($s->effectiveTimezone())->format('H:i');
        $today = today($s->effectiveTimezone())->toDateString();
        $admins = $this->admins();
        $sent = 0;

        if ($admins->isEmpty()) {
            return 0;
        }

        // WFH tomorrow.
        if ($force || ($s->wfh_enabled && $nowHm === $s->timeLabel('wfh_send_time')
                && optional($s->wfh_last_sent_on)->toDateString() !== $today)) {
            $people = $reminders->wfhOn();
            if ($people->isNotEmpty()) {
                $this->send($admins, 'wfh_tomorrow', $people);
                $sent++;
            }
            if (!$force) {
                $s->update(['wfh_last_sent_on' => $today]);
            }
        }

        // Late today.
        if ($force || ($s->late_enabled && $nowHm === $s->timeLabel('late_send_time')
                && optional($s->late_last_sent_on)->toDateString() !== $today)) {
            $people = $reminders->lateOn();
            if ($people->isNotEmpty()) {
                $this->send($admins, 'late_today', $people);
                $sent++;
            }
            if (!$force) {
                $s->update(['late_last_sent_on' => $today]);
            }
        }

        return $sent;
    }

    private function admins(): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->where('account_status', 'active')
            ->get();
    }

    private function send(Collection $admins, string $kind, Collection $people): void
    {
        foreach ($admins as $admin) {
            try {
                $admin->notify(new AdminDailyReminder($kind, $people));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
