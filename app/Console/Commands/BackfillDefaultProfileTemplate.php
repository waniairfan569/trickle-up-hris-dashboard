<?php

namespace App\Console\Commands;

use App\Models\ProfileTemplate;
use App\Models\Tenant;
use App\Services\DefaultProfileTemplateProvisioner;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Ensures every workspace owns the standard "Default Employee Profile" template.
 * New signups get it automatically at provisioning; run this once after deploying
 * to give already-existing workspaces the same default. Idempotent.
 */
class BackfillDefaultProfileTemplate extends Command
{
    protected $signature = 'profile-template:backfill {--tenant= : Limit to one tenant slug}';

    protected $description = 'Provision the Default Employee Profile template for tenants that are missing it';

    public function handle(TenantManager $tenants, DefaultProfileTemplateProvisioner $provisioner): int
    {
        $query = Tenant::withoutGlobalScopes();
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $created = 0;
        foreach ($query->get() as $tenant) {
            $tenants->set($tenant);

            if (ProfileTemplate::where('slug', 'default-employee-profile')->exists()) {
                $this->line("• {$tenant->slug}: already has it — skipped");
                continue;
            }

            $template = $provisioner->provisionForCurrentTenant();
            if ($template) {
                $created++;
                $this->info("✓ {$tenant->slug}: default template provisioned");
            } else {
                $this->warn("! {$tenant->slug}: blueprint missing — nothing provisioned");
            }
        }

        $this->newLine();
        $this->info("Done. Provisioned {$created} workspace(s).");

        return self::SUCCESS;
    }
}
