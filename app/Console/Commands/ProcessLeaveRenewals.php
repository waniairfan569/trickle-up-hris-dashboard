<?php

namespace App\Console\Commands;

use App\Models\LeaveYearSetting;
use App\Services\LeaveRenewalService;
use Illuminate\Console\Command;

class ProcessLeaveRenewals extends Command
{
    protected $signature = 'leave:process-renewals
        {--company= : Only this company_entity_id}
        {--policy= : Only this policy_id}
        {--dry-run : Preview only, no changes}
        {--force : Run even if not due yet}';

    protected $description = 'Run due leave-year renewals (encashment + carry forward + new balances).';

    public function handle(LeaveRenewalService $service): int
    {
        $query = LeaveYearSetting::where('is_active', true)->with('policy');

        if ($this->option('company')) {
            $query->where('company_entity_id', $this->option('company'));
        }
        if ($this->option('policy')) {
            $query->where('policy_id', $this->option('policy'));
        }
        if (!$this->option('force')) {
            $query->where('auto_renewal_enabled', true)->whereDate('next_renewal_date', '<=', today());
        }

        $settings = $query->get();
        if ($settings->isEmpty()) {
            $this->info('No leave-year renewals due.');
            return self::SUCCESS;
        }

        foreach ($settings as $setting) {
            $label = ($setting->policy->name ?? ('policy #' . $setting->policy_id)) . ' — ' . $setting->getCurrentYearLabel();

            if ($this->option('dry-run')) {
                $rows = $service->previewRenewal($setting);
                $this->info("[dry-run] {$label}: " . count($rows) . ' employee(s)');
                foreach ($rows as $r) {
                    $this->line(sprintf(
                        '  %-28s alloc %5.1f  rem %5.1f  cap %5.1f  encash %5.1f  lapse %5.1f  PKR %s',
                        $r['employee']->full_name, $r['allocation'], $r['days_remaining'],
                        $r['encashment_cap'], $r['days_to_encash'], $r['days_lapsed'],
                        number_format($r['encashment_amount'], 2)
                    ));
                }
                continue;
            }

            try {
                $summary = $service->runRenewal($setting, 'automatic', null);
                $this->info("Renewed {$label}: {$summary['total_employees']} employee(s), "
                    . "{$summary['with_encashment']} with encashment, PKR "
                    . number_format($summary['total_amount'], 2) . ' total, '
                    . "{$summary['total_lapsed']} day(s) lapsed.");
            } catch (\Throwable $e) {
                $this->error("FAILED {$label}: {$e->getMessage()}");
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
