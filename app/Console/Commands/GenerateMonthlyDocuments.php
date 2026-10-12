<?php

namespace App\Console\Commands;

use App\Models\AttendanceExplanation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceReportSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HrDocumentAutoGenerator;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Drafts the month-end roll-up documents: for every template set to one a
 * month, one document per employee listing each date and the reason behind it.
 *
 * Runs on the 3rd rather than the 1st so attendance corrections have settled
 * and people have had a chance to answer the same-day lateness prompt.
 */
class GenerateMonthlyDocuments extends Command
{
    protected $signature = 'documents:monthly-reviews
        {--month= : The month to cover, as YYYY-MM (default: last month)}
        {--min= : Late days required (default: the workspace setting)}
        {--dry-run : List what would be drafted without creating anything}';

    protected $description = 'Draft the month-end roll-up documents (lateness, WFH, leave returns) for templates set to one a month.';

    public function handle(HrDocumentAutoGenerator $generator): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : Carbon::today()->subMonthNoOverflow()->startOfMonth();

        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $total = 0;

        $run = function () use ($generator, $month, &$total) {
            $total += $this->forCurrentTenant($generator, $month);
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

        $this->info(($this->option('dry-run') ? 'Would draft ' : 'Drafted ')
            . $total . ' monthly document(s) for ' . $month->format('F Y') . '.');

        return self::SUCCESS;
    }

    private function forCurrentTenant(HrDocumentAutoGenerator $generator, Carbon $month): int
    {
        $templates = $generator->monthlyTemplates();
        if ($templates->isEmpty()) {
            return 0;
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $created = 0;

        foreach ($templates as $template) {
            $created += $this->forTemplate($generator, $template, $start, $end);
        }

        return $created;
    }

    /** One document per employee for this template's events in the month. */
    private function forTemplate(HrDocumentAutoGenerator $generator, $template, Carbon $start, Carbon $end): int
    {
        // The threshold applies to lateness, where the point is repeated
        // offences. Leave documents are drafted whenever there was any leave.
        $min = $template->prefill === 'lateness'
            ? ($this->option('min') !== null
                ? max(0, (int) $this->option('min'))
                : (int) (AttendanceReportSettings::getSettings()->lateness_review_min_days ?? 3))
            : 1;

        $created = 0;
        foreach (User::where('account_status', '!=', 'deactivated')->get() as $employee) {
            $events = $generator->monthlyEventCount($template, $employee, $start, $end);
            if ($events < max(1, $min)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [{$template->name}] {$employee->full_name} — {$events} event(s)");
                $created++;
                continue;
            }

            $document = $generator->generateMonthlyFor($template, $employee, $start->copy());
            if (! $document) {
                continue;
            }

            // An existing document is handed back rather than duplicated, so
            // only count what this run actually created.
            if (! $document->wasRecentlyCreated) {
                $this->line("  [{$template->name}] {$employee->full_name} — already drafted (#{$document->id})");
                continue;
            }

            if ($template->prefill === 'lateness') {
                // Record which days this document accounts for, so the
                // employee's own page can show where their answer ended up.
                AttendanceExplanation::where('user_id', $employee->id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->whereNull('hr_document_id')
                    ->update(['hr_document_id' => $document->id]);
            }

            $this->line("  [{$template->name}] {$employee->full_name} — {$events} event(s) → document #{$document->id}");
            $created++;
        }

        return $created;
    }
}
