<?php

namespace App\Console\Commands;

use App\Models\CompanyForm;
use App\Models\FormSubmission;
use App\Notifications\FormAssigned;
use Illuminate\Console\Command;

/**
 * Opens every active monthly form for the current month: creates a fresh
 * pending submission for each assigned employee who doesn't have one for this
 * month yet, and notifies them. Idempotent — runs daily so a form assigned
 * mid-month, or a newly-joined employee, still gets the current month opened.
 */
class OpenMonthlyForms extends Command
{
    protected $signature = 'forms:open-monthly {--dry-run : List what would be opened without writing}';

    protected $description = 'Open monthly company forms for the current month and notify assigned employees';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $forms = CompanyForm::where('is_monthly', true)->where('status', 'active')->get();
        $opened = 0;
        $notified = 0;

        foreach ($forms as $form) {
            $period = $form->currentPeriod();
            foreach ($form->getAssignedUsersFor() as $user) {
                $exists = $form->submissions()
                    ->where('user_id', $user->id)->where('period', $period)->exists();
                if ($exists) {
                    continue;
                }

                $opened++;
                $this->line(($dry ? '[dry] ' : '') . "open “{$form->title}” {$period} for {$user->first_name} {$user->last_name}");
                if ($dry) {
                    continue;
                }

                FormSubmission::create([
                    'form_id' => $form->id,
                    'user_id' => $user->id,
                    'period' => $period,
                    'status' => 'pending',
                ]);
                try {
                    $user->notify(new FormAssigned($form, $period));
                    $notified++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "{$forms->count()} monthly form(s); {$opened} submission(s) opened, {$notified} notified.");

        return self::SUCCESS;
    }
}
