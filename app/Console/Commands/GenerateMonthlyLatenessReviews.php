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
 * Drafts one Lateness Review per employee for the month just gone, for anyone
 * at or over the threshold. Runs on the 3rd rather than the 1st so attendance
 * corrections have settled and people have had a chance to answer the same-day
 * prompt — the reasons they gave are what fills the document's table.
 */
class GenerateMonthlyLatenessReviews extends Command
{
    protected $signature = 'lateness:monthly-reviews
        {--month= : The month to cover, as YYYY-MM (default: last month)}
        {--min= : Late days required (default: the workspace setting)}
        {--dry-run : List what would be drafted without creating anything}';

    protected $description = 'Draft the month-end Lateness Review for employees who were late often enough.';

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
            . $total . ' Lateness Review(s) for ' . $month->format('F Y') . '.');

        return self::SUCCESS;
    }

    private function forCurrentTenant(HrDocumentAutoGenerator $generator, Carbon $month): int
    {
        $min = $this->option('min') !== null
            ? max(0, (int) $this->option('min'))
            : (int) (AttendanceReportSettings::getSettings()->lateness_review_min_days ?? 3);

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $counts = AttendanceRecord::where('status', 'late')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) AS late_days')
            ->get()
            ->filter(fn ($r) => $r->late_days >= max(1, $min));

        // Nothing to do here — say nothing. On a multi-tenant install this runs
        // once per workspace and most will have no one over the threshold.
        if ($counts->isEmpty()) {
            return 0;
        }

        if (! $generator->latenessTemplate()) {
            $this->warn('  ' . $counts->count() . ' employee(s) are over the threshold but no template is assigned to "Late days" — set it on the Documents page.');

            return 0;
        }

        $created = 0;
        foreach (User::whereIn('id', $counts->pluck('user_id'))->get() as $employee) {
            $lateDays = (int) $counts->firstWhere('user_id', $employee->id)->late_days;

            if ($this->option('dry-run')) {
                $this->line("  {$employee->full_name} — {$lateDays} late day(s)");
                $created++;
                continue;
            }

            $document = $generator->generateMonthlyLateness($employee, $start->copy());
            if (! $document) {
                continue;
            }

            // generateMonthlyLateness hands back an existing document rather
            // than making a second one, so only count what this run created.
            if (! $document->wasRecentlyCreated) {
                $this->line("  {$employee->full_name} — already drafted (#{$document->id})");
                continue;
            }

            // Mark which days this document accounts for, so the employee's own
            // page can show where their answer ended up.
            AttendanceExplanation::where('user_id', $employee->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->whereNull('hr_document_id')
                ->update(['hr_document_id' => $document->id]);

            $this->line("  {$employee->full_name} — {$lateDays} late day(s) → document #{$document->id}");
            $created++;
        }

        return $created;
    }
}
