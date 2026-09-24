<?php

namespace App\Console\Commands;

use App\Models\HrDocument;
use App\Models\HrDocumentTemplate;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * One-time cleanup: removes UNSENT (draft) "Return to Work" documents so they
 * regenerate correctly (only unplanned / sick / WFH leave — no longer planned).
 * Sent/signed documents are never touched. Run once after deploying the
 * unplanned-only change:  php artisan documents:cleanup-rtw-drafts
 */
class CleanupReturnToWorkDrafts extends Command
{
    protected $signature = 'documents:cleanup-rtw-drafts {--tenant= : Limit to one tenant slug} {--dry-run : Show the count without deleting}';

    protected $description = 'Delete unsent (draft) Return to Work documents so they regenerate correctly.';

    public function handle(): int
    {
        $manager = app(TenantManager::class);
        $dry = (bool) $this->option('dry-run');

        $tenants = $this->option('tenant')
            ? Tenant::where('slug', $this->option('tenant'))->get()
            : Tenant::all();

        $total = 0;
        $process = function () use (&$total, $dry) {
            $template = HrDocumentTemplate::where('prefill', 'absence')->orderByDesc('is_active')->orderBy('id')->first();
            if (! $template) {
                return;
            }
            $drafts = HrDocument::where('hr_document_template_id', $template->id)
                ->where('status', 'draft')
                ->get();
            foreach ($drafts as $draft) {
                if (! $dry) {
                    $draft->signers()->delete();
                    $draft->delete();
                }
                $total++;
            }
        };

        if ($tenants->count() <= 1) {
            $manager->set($tenants->first());
            $process();
        } else {
            foreach ($tenants as $tenant) {
                $manager->set($tenant);
                $process();
            }
        }

        $manager->set(null);
        $this->info(($dry ? '[dry-run] would delete ' : 'Deleted ') . $total . ' Return to Work draft(s). Run "Generate missing now" (or wait for the nightly job) to recreate the correct ones.');

        return self::SUCCESS;
    }
}
