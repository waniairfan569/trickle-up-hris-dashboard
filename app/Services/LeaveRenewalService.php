<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveEncashmentRecord;
use App\Models\LeaveRenewalLog;
use App\Models\LeaveYearSetting;
use App\Models\TimeOffBalance;
use App\Models\TimeOffPolicy;
use App\Models\User;
use App\Notifications\LeaveRenewalNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leave-year renewal + encashment + pro-rata engine.
 *
 * Encashment rule: the cap is a function of the ANNUAL ALLOCATION (or the
 * pro-rata base for mid-year joiners), never of the remaining days:
 *   cap            = allocation × (percent / 100)   (or fixed days / full / none)
 *   days_to_encash = min(remaining, cap)
 *   amount         = days_to_encash × (monthly_salary / working_days_per_month)
 * Anything above the cap simply lapses.
 *
 * Balance-year convention (fits the app's calendar-keyed TimeOffBalance):
 *   closing balance = year of the leave-year's LAST day  (renewal - 1 day)
 *   new balance     = year of the NEW leave-year's start (renewal date)
 * For a Jan-1 leave year these are consecutive years (perfect alignment); for
 * mid-year starts (e.g. July) they share a key and the row is refreshed with
 * the new opening after the old remaining has been snapshotted.
 */
class LeaveRenewalService
{
    /** Scheduler entry point — run every due, auto-enabled setting. */
    public function checkAndRunDueRenewals(): void
    {
        $due = LeaveYearSetting::where('is_active', true)
            ->where('auto_renewal_enabled', true)
            ->whereDate('next_renewal_date', '<=', today())
            ->get();

        foreach ($due as $setting) {
            try {
                $this->runRenewal($setting, 'automatic', null);
            } catch (\Throwable $e) {
                report($e); // one failed setting must not block the rest
            }
        }
    }

    /** Execute a full renewal for one setting. Returns a summary array. */
    public function runRenewal(LeaveYearSetting $setting, string $triggeredBy = 'manual', ?User $by = null): array
    {
        $setting->loadMissing('policy');
        $policy = $setting->policy;
        $renewalDate = ($setting->next_renewal_date && $setting->next_renewal_date->lte(today()))
            ? $setting->next_renewal_date->copy()
            : today();
        $yearLabel = $setting->getCurrentYearLabel();
        $renewalYear = $renewalDate->year;

        $log = LeaveRenewalLog::create([
            'company_entity_id' => $setting->company_entity_id,
            'policy_id' => $policy->id,
            'leave_year_setting_id' => $setting->id,
            'renewal_date' => $renewalDate,
            'leave_year_label' => $yearLabel,
            'triggered_by' => $triggeredBy,
            'triggered_by_user_id' => $by?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $employees = $this->rosterFor($policy);

            $withEncash = 0;
            $noEncash = 0;
            $totalAmount = 0.0;
            $totalLapsed = 0.0;

            foreach ($employees as $employee) {
                $result = $this->processEmployee($employee, $setting, $policy, $yearLabel, $renewalYear, $renewalDate);
                $result['has_encashment'] ? $withEncash++ : $noEncash++;
                $totalAmount += $result['encashment_amount'];
                $totalLapsed += $result['days_lapsed'];
            }

            $setting->last_renewal_date = $renewalDate;
            $setting->next_renewal_date = $setting->calculateNextRenewalDate();
            $setting->save();

            $log->update([
                'status' => 'completed',
                'total_employees' => $employees->count(),
                'employees_with_encashment' => $withEncash,
                'employees_no_encashment' => $noEncash,
                'total_encashment_amount' => round($totalAmount, 2),
                'total_days_lapsed' => round($totalLapsed, 1),
                'completed_at' => now(),
            ]);

            return [
                'log_id' => $log->id,
                'total_employees' => $employees->count(),
                'with_encashment' => $withEncash,
                'total_amount' => round($totalAmount, 2),
                'total_lapsed' => round($totalLapsed, 1),
            ];
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        }
    }

    /** Close one employee's year: encash, lapse, carry forward, open new year. */
    public function processEmployee(
        User $employee,
        LeaveYearSetting $setting,
        TimeOffPolicy $policy,
        string $yearLabel,
        int $renewalYear,
        ?Carbon $renewalDate = null
    ): array {
        $renewalDate = $renewalDate ?: today();
        $closingYearKey = $renewalDate->copy()->subDay()->year;
        $newYearKey = $renewalDate->year;

        // 1. Closing-year balance.
        $balance = TimeOffBalance::where([
            'user_id' => $employee->id,
            'policy_id' => $policy->id,
            'year' => $closingYearKey,
        ])->first();

        // 2. Allocation — pro-rata when they joined inside this leave year.
        [$allocation, $isProRata, $proRataMonths] = $this->allocationFor($employee, $setting, $policy);

        // 3. Remaining at year end.
        $remaining = 0.0;
        if ($balance) {
            $remaining = max(0, (float) $balance->opening_balance
                + (float) $balance->accrued
                + (float) $balance->carried_over
                + (float) $balance->adjusted
                - (float) $balance->used
                - (float) $balance->pending);
        }
        $remaining = round($remaining, 1);

        // 4. Encashment — cap from the ALLOCATION, never the remaining.
        $daysToEncash = 0.0;
        $encashmentAmount = 0.0;
        $encashmentCap = 0.0;
        $hasEncashment = false;
        $dailyRate = 0.0;
        $salary = 0.0;

        if ($setting->encashment_enabled && $setting->encashment_type !== 'none' && $remaining > 0) {
            $encashmentCap = $setting->calculateEncashmentCap($allocation);
            $daysToEncash = round(min($remaining, $encashmentCap), 1);

            if ($daysToEncash > 0) {
                $hasEncashment = true;
                $salary = $this->getEmployeeMonthlySalary($employee);
                $dailyRate = $setting->working_days_per_month > 0
                    ? round($salary / $setting->working_days_per_month, 2) : 0.0;
                $encashmentAmount = round($daysToEncash * $dailyRate, 2);

                LeaveEncashmentRecord::create([
                    'company_entity_id' => $setting->company_entity_id,
                    'user_id' => $employee->id,
                    'policy_id' => $policy->id,
                    'leave_year_setting_id' => $setting->id,
                    'leave_year_label' => $yearLabel,
                    'renewal_year' => $renewalYear,
                    'annual_allocation' => $allocation,
                    'is_pro_rata' => $isProRata,
                    'pro_rata_months' => $proRataMonths,
                    'days_remaining_before_renewal' => $remaining,
                    'encashment_type' => $setting->encashment_type,
                    'encashment_value' => $setting->encashment_value,
                    'encashment_cap_days' => $encashmentCap === PHP_FLOAT_MAX ? $remaining : round($encashmentCap, 1),
                    'days_to_encash' => $daysToEncash,
                    'daily_rate' => $dailyRate,
                    'monthly_salary_snapshot' => $salary,
                    'encashment_amount' => $encashmentAmount,
                    'days_lapsed' => round($remaining - $daysToEncash, 1),
                    'currency' => 'PKR',
                    'status' => 'pending',
                ]);
            }
        }

        // 5. Carry forward (never the days that were just encashed).
        $carryForward = 0.0;
        if ($setting->carry_forward_enabled) {
            $carryForward = $setting->carry_forward_max_days !== null
                ? min($remaining, (float) $setting->carry_forward_max_days)
                : $remaining;
            $carryForward = round(max(0, $carryForward - $daysToEncash), 1);
        }

        // 5.5 Close the old year for real: zero its usable remainder so nothing
        //     can still be spent from the lapsed year.
        if ($balance) {
            $balance->adjusted = (float) $balance->adjusted - $remaining;
            $balance->save();
        }

        // 6. Open the new year: fresh full allocation (+ carry forward).
        $newAllocation = (float) $policy->getAllowanceForUser($employee);
        TimeOffBalance::updateOrCreate(
            ['user_id' => $employee->id, 'policy_id' => $policy->id, 'year' => $newYearKey],
            [
                'opening_balance' => $newAllocation,
                'carried_over' => $carryForward,
                'accrued' => 0, 'used' => 0, 'pending' => 0, 'adjusted' => 0,
            ]
        );

        // 7. Tell the employee.
        try {
            $employee->notify(new LeaveRenewalNotification(
                $policy, $yearLabel, $allocation, $remaining,
                $encashmentCap === PHP_FLOAT_MAX ? $remaining : $encashmentCap,
                $daysToEncash, $encashmentAmount, round($remaining - $daysToEncash, 1),
                $carryForward, $newAllocation, $isProRata, $proRataMonths, $setting
            ));
        } catch (\Throwable $e) {
            report($e); // a mail failure must never abort the renewal
        }

        return [
            'has_encashment' => $hasEncashment,
            'encashment_amount' => $encashmentAmount,
            'days_lapsed' => round($remaining - $daysToEncash, 1),
            'carry_forward' => $carryForward,
        ];
    }

    /** DRY RUN — everything computed, nothing written. */
    public function previewRenewal(LeaveYearSetting $setting): array
    {
        $setting->loadMissing('policy');
        $policy = $setting->policy;
        $renewalDate = ($setting->next_renewal_date && $setting->next_renewal_date->lte(today()))
            ? $setting->next_renewal_date->copy() : today();
        $closingYearKey = $renewalDate->copy()->subDay()->year;

        $rows = [];
        foreach ($this->rosterFor($policy) as $employee) {
            [$allocation, $isProRata, $proRataMonths] = $this->allocationFor($employee, $setting, $policy);

            $balance = TimeOffBalance::where([
                'user_id' => $employee->id, 'policy_id' => $policy->id, 'year' => $closingYearKey,
            ])->first();
            $remaining = 0.0;
            if ($balance) {
                $remaining = max(0, (float) $balance->opening_balance + (float) $balance->accrued
                    + (float) $balance->carried_over + (float) $balance->adjusted
                    - (float) $balance->used - (float) $balance->pending);
            }
            $remaining = round($remaining, 1);

            $cap = ($setting->encashment_enabled && $setting->encashment_type !== 'none')
                ? $setting->calculateEncashmentCap($allocation) : 0.0;
            $daysToEncash = round(min($remaining, $cap), 1);
            $salary = $this->getEmployeeMonthlySalary($employee);
            $dailyRate = $setting->working_days_per_month > 0 ? $salary / $setting->working_days_per_month : 0;
            $carry = 0.0;
            if ($setting->carry_forward_enabled) {
                $carry = $setting->carry_forward_max_days !== null
                    ? min($remaining, (float) $setting->carry_forward_max_days) : $remaining;
                $carry = round(max(0, $carry - $daysToEncash), 1);
            }
            $newAllocation = (float) $policy->getAllowanceForUser($employee);

            $rows[] = [
                'employee' => $employee,
                'allocation' => $allocation,
                'is_pro_rata' => $isProRata,
                'used' => $balance ? round((float) $balance->used, 1) : 0.0,
                'days_remaining' => $remaining,
                'encashment_cap' => $cap === PHP_FLOAT_MAX ? $remaining : round($cap, 1),
                'days_to_encash' => $daysToEncash,
                'days_lapsed' => round($remaining - $daysToEncash, 1),
                'encashment_amount' => round($daysToEncash * $dailyRate, 2),
                'carry_forward' => $carry,
                'new_balance' => round($newAllocation + $carry, 1),
            ];
        }

        return $rows;
    }

    /** Pro-rata days for a joiner: months from (cutoff-adjusted) join month to year end. */
    public function calculateProRataDays(float $annualDays, Carbon $joiningDate, LeaveYearSetting $setting): float
    {
        $yearStart = $setting->currentYearStart();
        $yearEnd = $yearStart->copy()->addYear()->subDay();

        if ($joiningDate->lte($yearStart)) {
            return $annualDays;                    // served the whole year
        }
        if ($joiningDate->gt($yearEnd)) {
            return 0.0;                            // joined after this year
        }

        $months = $this->countRemainingMonths($joiningDate, $setting);
        $proRata = ($annualDays / 12) * $months;

        return match ($setting->pro_rata_round_to) {
            'half' => round($proRata * 2) / 2,
            'full' => (float) ceil($proRata),
            default => round($proRata, 1),
        };
    }

    /** Months counted from the join month (respecting the cutoff day) to year end. */
    public function countRemainingMonths(Carbon $joiningDate, LeaveYearSetting $setting): int
    {
        $yearStart = $setting->currentYearStart();
        $yearEnd = $yearStart->copy()->addYear()->subDay();

        $countFrom = $joiningDate->day > $setting->pro_rata_cutoff_day
            ? $joiningDate->copy()->addMonthNoOverflow()->startOfMonth()
            : $joiningDate->copy()->startOfMonth();

        $months = (int) round($countFrom->diffInMonths($yearEnd->copy()->addDay()));

        return max(0, min(12, $months));
    }

    /** Pro-rata (or full) opening balance for a brand-new joiner. */
    public function assignLeaveOnJoining(User $employee, LeaveYearSetting $setting, TimeOffPolicy $policy): TimeOffBalance
    {
        $joiningDate = $this->startDateOf($employee) ?: today();

        $allocation = $setting->pro_rata_enabled
            ? $this->calculateProRataDays((float) $policy->days_per_year, $joiningDate, $setting)
            : (float) $policy->days_per_year;

        return TimeOffBalance::firstOrCreate(
            ['user_id' => $employee->id, 'policy_id' => $policy->id, 'year' => now()->year],
            [
                'opening_balance' => $allocation,
                'accrued' => 0, 'used' => 0, 'pending' => 0, 'carried_over' => 0, 'adjusted' => 0,
            ]
        );
    }

    /**
     * The opening allocation for an employee under a policy for the CURRENT leave
     * year — the exact number the renewal preview shows: pro-rata for a mid-year
     * joiner (joined after the leave-year start), otherwise their full allowance.
     * Single source of truth so a stored balance always matches the preview.
     */
    public function currentAllocationFor(User $employee, TimeOffPolicy $policy, ?LeaveYearSetting $setting = null): float
    {
        $setting = $setting ?: LeaveYearSetting::where('policy_id', $policy->id)
            ->where('is_active', true)
            ->when($employee->tenant_id, fn ($q) => $q->where('tenant_id', $employee->tenant_id))
            ->first();

        if (!$setting || !$setting->pro_rata_enabled) {
            return (float) $policy->getAllowanceForUser($employee);
        }

        $startDate = $this->startDateOf($employee);

        if ($startDate && $startDate->gt($setting->currentYearStart())) {
            return $this->calculateProRataDays((float) $policy->days_per_year, $startDate, $setting);
        }

        return (float) $policy->getAllowanceForUser($employee);
    }

    /** Monthly salary for the daily-rate calculation. */
    public function getEmployeeMonthlySalary(User $user): float
    {
        $raw = $user->getFieldValue('salary');
        if ($raw === null || $raw === '') {
            $raw = $user->salary;
        }

        return (float) ($raw ?: 0);
    }

    // ---------------------------------------------------------------------

    /** [allocation, isProRata, months] for the leave year now closing. */
    private function allocationFor(User $employee, LeaveYearSetting $setting, TimeOffPolicy $policy): array
    {
        $startDate = $this->startDateOf($employee);
        $yearStart = $setting->currentYearStart();

        if ($setting->pro_rata_enabled && $startDate && $startDate->gt($yearStart)) {
            return [
                $this->calculateProRataDays((float) $policy->days_per_year, $startDate, $setting),
                true,
                $this->countRemainingMonths($startDate, $setting),
            ];
        }

        return [(float) $policy->getAllowanceForUser($employee), false, null];
    }

    private function startDateOf(User $user): ?Carbon
    {
        $raw = $user->joined_at ?? $user->hire_date;

        try {
            return $raw ? Carbon::parse($raw)->startOfDay() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Everyone this policy applies to: pivot assignees + balance holders. Active, non-system. */
    private function rosterFor(TimeOffPolicy $policy)
    {
        $systemUserIds = Employee::where('is_system', true)->pluck('user_id')->filter()->all();

        $ids = collect($policy->employees()->pluck('users.id')->all())
            ->merge(TimeOffBalance::where('policy_id', $policy->id)->pluck('user_id')->all())
            ->unique()->values();

        return User::whereIn('id', $ids->all())
            ->where('account_status', '!=', 'deactivated')
            ->whereNotIn('id', $systemUserIds)
            ->orderBy('first_name')
            ->get();
    }
}
