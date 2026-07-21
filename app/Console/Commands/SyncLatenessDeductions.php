<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Services\LatenessDeductionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncLatenessDeductions extends Command
{
    protected $signature = 'attendance:sync-lateness {--month= : YYYY-MM to sync (defaults to current month)}';

    protected $description = 'Recompute lateness leave deductions (4 lates -> 0.5 day, 6 -> 1 day) for all employees.';

    public function handle(LatenessDeductionService $service): int
    {
        $date = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : Carbon::today();

        $userIds = Employee::real()->pluck('user_id')->filter();
        $users = User::whereIn('id', $userIds->all())
            ->where('account_status', '!=', 'deactivated')
            ->where('exclude_from_attendance', false)
            ->get();

        $this->info("Syncing lateness deductions for {$date->format('F Y')} ({$users->count()} employees)...");
        foreach ($users as $user) {
            try {
                $service->sync($user, $date);
            } catch (\Throwable $e) {
                report($e);
                $this->warn("  failed for {$user->id}: {$e->getMessage()}");
            }
        }
        $this->info('Done.');

        return self::SUCCESS;
    }
}
