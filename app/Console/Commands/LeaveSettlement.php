<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Services\LeaveRenewalService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * What a leaver has earned by their last day, against what they took.
 *
 * Pro-rata was only ever worked out for joiners. Somebody leaving in March
 * kept the whole year's allowance on paper, so whether they were owed days or
 * had taken them in advance was settled by hand, in a spreadsheet, from
 * memory.
 *
 * This reads; it changes nothing. Paying or reclaiming days is a decision with
 * money attached, and it belongs to a person rather than to a command.
 */
class LeaveSettlement extends Command
{
    protected $signature = 'leave:settlement
                            {employee : Email, employee id, or user id}
                            {--date= : Last working day (default: their recorded termination date)}
                            {--apply : Write it to their balances, instead of only reporting}
                            {--again : Settle again, even if it has been settled before}
                            {--force : Skip the confirmation}';

    protected $description = 'Work out what a leaver is owed, or has taken in advance.';

    public function handle(LeaveRenewalService $service): int
    {
        $employee = $this->findUser((string) $this->argument('employee'));

        if (! $employee) {
            $this->error('No employee matches "' . $this->argument('employee') . '".');

            return self::FAILURE;
        }

        $leaving = $this->leavingDate($employee);

        if (! $leaving) {
            $this->error('No last working day. Pass --date=YYYY-MM-DD, or set a termination date on their record.');

            return self::FAILURE;
        }

        $rows = $service->settlementFor($employee, $leaving);

        $this->line('');
        $this->info("  {$employee->full_name}  <{$employee->email}>");
        $this->line('  last working day: ' . $leaving->format('j F Y'));
        $this->line('');

        if (empty($rows)) {
            $this->warn('  They hold no leave balances, so there is nothing to settle.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->line(sprintf('   %-26s %8s %8s %8s %9s',
            'POLICY', 'MONTHS', 'EARNED', 'TAKEN', 'OWED'));

        foreach ($rows as $row) {
            $diff = $row['difference'];

            $this->line(sprintf('   %-26s %8s %8s %8s %9s   %s',
                mb_substr($row['policy'], 0, 26),
                $row['months_served'] . '/12',
                $row['earned'],
                $row['taken'],
                $diff > 0 ? '+' . $diff : $diff,
                $this->reading($diff)
            ));
        }

        $this->line('');
        $this->line('  "Earned" is their allowance pro-rated to the months they served, plus');
        $this->line('  anything carried over or adjusted by hand — those were already earned.');

        if (! $this->option('apply')) {
            $this->line('  Nothing has been changed. Add --apply to write it to their balances.');
            $this->line('');

            return self::SUCCESS;
        }

        // ---- writing it ----
        $settled = collect($rows)->filter(fn ($r) => $r['balance']->settled_at)->count();

        $this->line('');
        $this->warn('  This adjusts their balances so each year grants what they actually earned.');
        $this->line('  Leave already taken is untouched; nothing is deleted.');

        if ($settled > 0 && ! $this->option('again')) {
            $this->line("  {$settled} of these were settled before and will be left alone (--again to redo).");
        }

        if (! $this->option('force') && ! $this->confirm('  Write it?', false)) {
            $this->line('  Nothing was changed.');
            $this->line('');

            return self::SUCCESS;
        }

        $applied = $service->applySettlement($employee, $leaving, auth()->user(), (bool) $this->option('again'));

        $this->line('');
        foreach ($applied as $row) {
            if (isset($row['skipped'])) {
                $this->line(sprintf('   %-26s  left alone — %s', mb_substr($row['policy'], 0, 26), $row['skipped']));
                continue;
            }

            $this->line(sprintf('   %-26s  adjusted by %s   remaining now %s',
                mb_substr($row['policy'], 0, 26),
                $row['delta'] > 0 ? '+' . $row['delta'] : $row['delta'],
                round($row['balance']->fresh()->remaining, 2)
            ));
        }

        $this->line('');
        $this->info('  Written. Their remaining balance is now what they are owed.');
        $this->line('');

        return self::SUCCESS;
    }

    private function reading(float $diff): string
    {
        return match (true) {
            $diff > 0 => "<fg=green>owed {$diff} day(s)</>",
            $diff < 0 => '<fg=yellow>took ' . abs($diff) . ' day(s) in advance</>',
            default => 'square',
        };
    }

    private function leavingDate(User $employee): ?Carbon
    {
        if ($this->option('date')) {
            try {
                return Carbon::parse($this->option('date'))->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        $recorded = Employee::where('user_id', $employee->id)->value('termination_date');

        try {
            return $recorded ? Carbon::parse($recorded)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function findUser(string $needle): ?User
    {
        return (is_numeric($needle) ? User::withoutGlobalScopes()->where('id', $needle)->first() : null)
            ?: User::withoutGlobalScopes()->where('email', $needle)->first()
            ?: User::withoutGlobalScopes()->where('employee_id', $needle)->first();
    }
}
