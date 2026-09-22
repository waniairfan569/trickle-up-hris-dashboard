<?php

namespace App\Console\Commands;

use App\Models\AdminReminderSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OvertimeReportReminder;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Fires the recurring approved-overtime report reminder each workspace configured
 * (monthly on day N, or weekly), at its send time. Runs every minute; guarded to
 * send once per cadence period.
 */
class SendOvertimeReportReminders extends Command
{
    protected $signature = 'reminders:overtime-report {--force : Send now, ignoring schedule/once-per-period guards}';

    protected $description = 'Send the recurring approved-overtime report reminder to finance/admins.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $sent = 0;

        if ($tenants->count() <= 1) {
            $manager->set(null);
            $sent += $this->process($force);
        } else {
            foreach ($tenants as $tenant) {
                $manager->set($tenant);
                $sent += $this->process($force);
            }
        }

        $manager->set(null);
        $this->info("Overtime report reminders: {$sent} notification(s) sent.");

        return self::SUCCESS;
    }

    private function process(bool $force): int
    {
        $s = AdminReminderSetting::getSettings();

        if ($force ? ! $s->overtime_enabled : ! $s->overtimeDueNow()) {
            return 0;
        }

        $recipients = $this->recipients($s);
        if ($recipients->isEmpty()) {
            return 0;
        }

        foreach ($recipients as $user) {
            try {
                $user->notify(new OvertimeReportReminder($s->overtimeScheduleLabel()));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (! $force) {
            $s->update(['overtime_last_sent_key' => $s->overtimeSendKey()]);
        }

        return $recipients->count();
    }

    private function recipients(AdminReminderSetting $s): Collection
    {
        $ids = $s->overtime_recipients ?? [];

        $query = ! empty($ids)
            ? User::whereIn('id', $ids)
            : User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']));

        return $query->where('account_status', 'active')->get();
    }
}
