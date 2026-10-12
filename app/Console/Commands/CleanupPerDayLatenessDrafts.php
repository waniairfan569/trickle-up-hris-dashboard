<?php

namespace App\Console\Commands;

use App\Models\HrDocument;
use App\Models\Tenant;
use App\Services\HrDocumentAutoGenerator;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Clears the backlog left by the old one-document-per-late-day drafting.
 *
 * Only DRAFTS are touched — anything sent, signed or completed is a real
 * record of a conversation that happened and is left exactly where it is.
 * Deletion is the same soft delete as the Documents page, so everything
 * stays restorable from the Deleted list.
 */
class CleanupPerDayLatenessDrafts extends Command
{
    protected $signature = 'documents:cleanup-lateness-drafts
        {--before= : Only drafts for days before this date (YYYY-MM-DD)}
        {--dry-run : List what would be removed without touching anything}';

    protected $description = 'Remove unsent per-day Lateness Review drafts left over from the old flow.';

    public function handle(HrDocumentAutoGenerator $generator): int
    {
        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $total = 0;

        $run = function () use ($generator, &$total) {
            $total += $this->forCurrentTenant($generator);
        };

        if ($tenants->count() <= 1) {
            $manager->set(null);
            $run();
        } else {
            foreach ($tenants as $tenant) {
                $manager->set($tenant);
                $run();
            }
        }
        $manager->set(null);

        $this->info(($this->option('dry-run') ? 'Would remove ' : 'Moved ')
            . $total . ' per-day draft(s)'
            . ($this->option('dry-run') ? '.' : ' to Deleted — restorable from the Deleted list.'));

        return self::SUCCESS;
    }

    private function forCurrentTenant(HrDocumentAutoGenerator $generator): int
    {
        $template = $generator->latenessTemplate();
        if (! $template) {
            return 0;
        }

        $query = HrDocument::where('hr_document_template_id', $template->id)
            ->where('status', 'draft')
            // Per-day: the period is a single day. Monthly reviews span a month
            // and are the documents this flow now produces.
            ->whereColumn('period_start', 'period_end')
            ->with('employee');

        if ($this->option('before')) {
            $query->whereDate('period_start', '<', $this->option('before'));
        }

        $drafts = $query->orderBy('period_start')->get();
        if ($drafts->isEmpty()) {
            return 0;
        }

        foreach ($drafts->groupBy('user_id') as $rows) {
            $name = optional($rows->first()->employee)->full_name ?? 'Employee';
            $span = optional($rows->first()->period_start)->format('d M Y')
                . ' – ' . optional($rows->last()->period_start)->format('d M Y');
            $this->line("  {$name} — {$rows->count()} draft(s), {$span}");
        }

        if ($this->option('dry-run')) {
            return $drafts->count();
        }

        foreach ($drafts as $draft) {
            $draft->signers()->delete();
            $draft->delete();
        }

        return $drafts->count();
    }
}
