<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\LatenessDeduction;
use App\Models\TimeOffPolicy;
use App\Models\User;
use Carbon\Carbon;

/**
 * Applies the contractual lateness penalty to unplanned leave:
 *   - 4 late arrivals in a month  -> half a day (0.5) deducted
 *   - 6 late arrivals in a month  -> a full day (1.0) deducted
 * (1-3 lates are free; the deduction is a running total, so going from 4 to 6
 *  adds the second half-day.)
 *
 * Idempotent: it records how much has already been deducted for the month and
 * only applies the delta, so it can be called on every clock-in / punch / daily
 * run without double-charging.
 */
class LatenessDeductionService
{
    public function __construct(private TimeOffBalanceService $balances)
    {
    }

    /** Deduction owed for a given number of late days in a month. */
    public static function penaltyFor(int $lateCount): float
    {
        if ($lateCount >= 6) {
            return 1.0;
        }
        if ($lateCount >= 4) {
            return 0.5;
        }

        return 0.0;
    }

    /** Recompute and apply the lateness deduction for a user's month. */
    public function sync(User $user, ?Carbon $date = null): void
    {
        $date = $date ?: Carbon::today();
        $year = (int) $date->year;
        $month = (int) $date->month;

        $policy = $this->unplannedPolicyFor($user);
        if (!$policy) {
            return; // no unplanned-leave policy to deduct from
        }

        $lateCount = AttendanceRecord::where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('status', 'late')
            ->count();

        $target = self::penaltyFor($lateCount);

        $record = LatenessDeduction::firstOrNew([
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ]);
        $already = (float) ($record->days_deducted ?? 0);
        $delta = round($target - $already, 1);

        if (abs($delta) >= 0.01) {
            $balance = $this->balances->getOrCreateBalance($user, $policy, $year);
            if ($delta > 0) {
                $balance->increment('used', $delta);
            } else {
                $balance->decrement('used', abs($delta));
            }
        }

        $record->fill([
            'late_count' => $lateCount,
            'days_deducted' => $target,
            'policy_id' => $policy->id,
        ])->save();
    }

    /**
     * The employee's unplanned-leave policy. Matches by name the same way the
     * dashboard does (Unplanned / Casual / Sick / Emergency), preferring one the
     * employee is actually assigned.
     */
    public function unplannedPolicyFor(User $user): ?TimeOffPolicy
    {
        $match = function ($query) {
            $query->where(function ($q) {
                foreach (['unplanned', 'casual', 'sick', 'emergency'] as $kw) {
                    $q->orWhere('name', 'like', "%{$kw}%");
                }
            });
        };

        if (method_exists($user, 'timeOffPolicies')) {
            $assigned = $user->timeOffPolicies()->where($match)->first();
            if ($assigned) {
                return $assigned;
            }
        }

        return TimeOffPolicy::where($match)->first();
    }
}
