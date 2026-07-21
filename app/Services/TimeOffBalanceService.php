<?php

namespace App\Services;

use App\Models\TimeOffBalance;
use App\Models\TimeOffPolicy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimeOffBalanceService
{
    /**
     * Assign every auto-assignable time-off policy to a (new) employee and seed
     * their balance for the current year. Idempotent — safe to call more than
     * once. Scoped to the employee's own tenant so nothing leaks across
     * agencies when run outside a web request (e.g. console/seed).
     */
    public function assignDefaultPolicies(User $user, ?int $assignedBy = null): void
    {
        // autoAssignable() keeps soft-deletes excluded; the explicit tenant
        // filter scopes correctly even in console context (TenantScope is a
        // no-op when no current tenant is set).
        $policies = TimeOffPolicy::autoAssignable()
            ->when($user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->get();

        if ($policies->isEmpty()) {
            return;
        }

        $year = Carbon::now()->year;

        foreach ($policies as $policy) {
            $policy->employees()->syncWithoutDetaching([
                $user->id => [
                    'assigned_by' => $assignedBy,
                    'assigned_at' => now(),
                    'effective_from' => now(),
                ],
            ]);

            $this->getOrCreateBalance($user, $policy, $year);
        }
    }

    /**
     * Get or create a balance record for a user, policy, and year.
     * If the policy has no accrual, it grants the full balance upfront.
     */
    public function getOrCreateBalance(User $user, TimeOffPolicy $policy, int $year): TimeOffBalance
    {
        return TimeOffBalance::firstOrCreate(
            [
                'user_id' => $user->id,
                'policy_id' => $policy->id,
                'year' => $year,
            ],
            [
                // If accrual_type is 'none', they get the full allowance upfront
                'opening_balance' => $policy->accrual_type === 'none' ? $policy->getAllowanceForUser($user) : 0,
                'accrued' => 0,
                'used' => 0,
                'pending' => 0,
                'carried_over' => 0,
                'adjusted' => 0,
            ]
        );
    }

    /**
     * Called by a scheduled job (e.g. monthly)
     */
    public function accrueMonthly(User $user, TimeOffPolicy $policy): void
    {
        if ($policy->accrual_type !== 'monthly') {
            return;
        }

        $year = Carbon::now()->year;
        $balance = $this->getOrCreateBalance($user, $policy, $year);

        $allowance = $policy->getAllowanceForUser($user);
        $monthlyAccrual = round($allowance / 12, 1);

        $newAccrued = $balance->accrued + $monthlyAccrual;

        // Cap at max_balance if defined
        if ($policy->max_balance !== null) {
            $totalIfAdded = $balance->opening_balance + $newAccrued + $balance->carried_over + $balance->adjusted;
            if ($totalIfAdded > $policy->max_balance) {
                // Adjust accrued so total == max_balance
                $allowedToAdd = $policy->max_balance - ($balance->opening_balance + $balance->carried_over + $balance->adjusted);
                $newAccrued = max(0, $allowedToAdd);
            }
        }

        $balance->update(['accrued' => $newAccrued]);
    }

    /**
     * Deduct from balance when request is APPROVED
     */
    public function deductBalance(User $user, TimeOffPolicy $policy, float $days): void
    {
        $year = Carbon::now()->year;
        $balance = $this->getOrCreateBalance($user, $policy, $year);

        $balance->increment('used', $days);
    }

    /**
     * Add to pending when request is SUBMITTED
     */
    public function addPending(User $user, TimeOffPolicy $policy, float $days): void
    {
        $year = Carbon::now()->year;
        $balance = $this->getOrCreateBalance($user, $policy, $year);

        $balance->increment('pending', $days);
    }

    /**
     * Remove from pending when request is APPROVED, REJECTED, or CANCELLED
     */
    public function removePending(User $user, TimeOffPolicy $policy, float $days): void
    {
        $year = Carbon::now()->year;
        $balance = $this->getOrCreateBalance($user, $policy, $year);

        $balance->decrement('pending', $days);
    }

    /**
     * Manual adjustment by HR Admin
     */
    public function manualAdjust(User $user, TimeOffPolicy $policy, float $days, string $note, User $adjustedBy): void
    {
        $year = Carbon::now()->year;
        $balance = $this->getOrCreateBalance($user, $policy, $year);

        $balance->increment('adjusted', $days);

        // Ideally we would also log this in an audit table with the $note and $adjustedBy->id
    }

    /**
     * Year End Rollover
     */
    public function yearEndRollover(int $currentYear): void
    {
        $policies = TimeOffPolicy::with('employees')->active()->get();
        $nextYear = $currentYear + 1;

        DB::transaction(function () use ($policies, $currentYear, $nextYear) {
            foreach ($policies as $policy) {
                foreach ($policy->employees as $user) {
                    $currentBalance = TimeOffBalance::where('user_id', $user->id)
                        ->where('policy_id', $policy->id)
                        ->where('year', $currentYear)
                        ->first();

                    $carryOverAmount = 0;

                    if ($currentBalance && $policy->carry_over) {
                        $remaining = $currentBalance->remaining;
                        
                        if ($remaining > 0) {
                            $carryOverAmount = $policy->carry_over_max !== null 
                                ? min($remaining, $policy->carry_over_max) 
                                : $remaining;
                        }
                    }

                    // Create next year's balance record
                    TimeOffBalance::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'policy_id' => $policy->id,
                            'year' => $nextYear,
                        ],
                        [
                            'opening_balance' => $policy->accrual_type === 'none' ? $policy->getAllowanceForUser($user) : 0,
                            'carried_over' => $carryOverAmount,
                        ]
                    );
                }
            }
        });
    }
}
