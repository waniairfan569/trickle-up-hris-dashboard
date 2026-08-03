<?php

namespace App\Console\Commands;

use App\Models\Probation;
use App\Services\ProbationNotifier;
use Illuminate\Console\Command;

class SendProbationCompletionNotices extends Command
{
    protected $signature = 'probation:notify-completions {--dry : Report only, do not send}';

    protected $description = 'Email the employee + admins when a probation period completes (end date reached).';

    public function handle(ProbationNotifier $notifier): int
    {
        $dry = (bool) $this->option('dry');

        // Probations whose period has ended, aren't failed, and haven't been notified yet.
        $due = Probation::where('status', '!=', 'failed')
            ->whereNull('completion_notified_at')
            ->whereDate('end_date', '<=', now()->toDateString())
            ->with('employee')
            ->get();

        $sent = 0;
        foreach ($due as $probation) {
            if (!$probation->employee) {
                continue;
            }
            if ($dry) {
                $this->line("[dry] would notify probation #{$probation->id} — {$probation->employee->email}");
                $sent++;
                continue;
            }
            if ($notifier->notifyCompletion($probation)) {
                $sent++;
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Notified {$sent} completed probation(s).");

        return self::SUCCESS;
    }
}
