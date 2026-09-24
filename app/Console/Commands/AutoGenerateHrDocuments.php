<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Tenant;
use App\Models\TimeOffRequest;
use App\Models\User;
use App\Services\HrDocumentAutoGenerator;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nightly: creates draft Lateness Review docs (one per late day) and Return to
 * Work Form drafts (per completed leave) for employees who had a late day or
 * returned from leave recently. Drafts land in the employee's profile for HR to
 * review/edit/send. The monthly consolidated Lateness Review is admin-triggered,
 * not here.
 */
class AutoGenerateHrDocuments extends Command
{
    protected $signature = 'documents:auto-generate {--lookback=45 : How many days back to catch up}';

    protected $description = 'Auto-create draft Lateness Review / Return to Work documents from attendance events.';

    public function handle(HrDocumentAutoGenerator $generator): int
    {
        $lookback = (int) $this->option('lookback');
        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $created = 0;

        $run = function () use ($generator, $lookback, &$created) {
            foreach ($this->candidates($lookback) as $employee) {
                $created += $generator->generateForEmployee($employee, $lookback);
            }
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
        $this->info("Auto-generated {$created} draft document(s).");

        return self::SUCCESS;
    }

    /** Only employees with a recent late day or a recently-ended leave (keeps it cheap). */
    private function candidates(int $lookback)
    {
        $from = Carbon::today()->subDays($lookback)->toDateString();
        $to = Carbon::today()->subDay()->toDateString();

        $lateIds = AttendanceRecord::where('status', 'late')
            ->whereBetween('date', [$from, $to])
            ->distinct()->pluck('user_id');

        $leaveIds = TimeOffRequest::where('status', 'approved')
            ->excludingWorkFromHome()
            ->whereDate('end_date', '<', Carbon::today()->toDateString())
            ->whereDate('end_date', '>=', $from)
            ->distinct()->pluck('user_id');

        $ids = $lateIds->merge($leaveIds)->unique()->values();

        return $ids->isEmpty() ? collect() : User::whereIn('id', $ids)->get();
    }
}
