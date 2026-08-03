<?php

namespace App\Console\Commands;

use App\Models\Probation;
use App\Models\User;
use App\Notifications\ProbationReviewsOverdue;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendProbationReviewReminders extends Command
{
    protected $signature = 'probation:review-reminders {--dry : Report only, do not send}';

    protected $description = 'Remind HR/admins about probation reviews that are overdue (period ended, not yet confirmed or failed).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $today = now()->startOfDay();

        // Still on "active" (undecided) but the period has already ended.
        $overdue = Probation::with('employee.department')
            ->where('status', 'active')
            ->whereDate('end_date', '<', $today->toDateString())
            ->whereHas('employee', fn ($q) => $q->where('account_status', '!=', 'deactivated'))
            ->orderBy('end_date')
            ->get()
            ->filter(fn ($p) => $p->employee)
            ->values();

        if ($overdue->isEmpty()) {
            $this->info('No overdue probation reviews.');

            return self::SUCCESS;
        }

        $items = $overdue->map(fn ($p) => [
            'name' => $p->employee->full_name,
            'department' => optional($p->employee->department)->name,
            'days' => (int) Carbon::parse($p->end_date)->startOfDay()->diffInDays($today),
            'user_id' => $p->user_id,
        ])->all();

        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
                ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();

        if ($dry) {
            $this->info("[dry-run] {$overdue->count()} overdue review(s) — would email {$admins->count()} admin(s).");
            foreach ($items as $it) {
                $this->line("  • {$it['name']} — {$it['days']} day(s) overdue");
            }

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($admins as $admin) {
            try {
                $admin->notify(new ProbationReviewsOverdue($items));
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->info("Notified {$sent} admin(s) about {$overdue->count()} overdue probation review(s).");

        return self::SUCCESS;
    }
}
