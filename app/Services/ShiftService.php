<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Exception;

class ShiftService
{
    /**
     * Get the exact shift assigned to a user for a specific date.
     * Prioritizes single-day overrides, then checks recurring assignments.
     */
    public function getShiftForUserOnDate(User $user, Carbon $date): ?Shift
    {
        // 1. Check for single-day assignment override
        $singleAssignment = $user->shiftAssignments()
            ->where('assignment_type', 'single')
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($singleAssignment) {
            return $singleAssignment->shift;
        }

        // 2. Check for recurring assignment covering this date
        $dayName = $date->format('D'); // "Mon", "Tue", etc.
        
        $recurringAssignment = $user->shiftAssignments()
            ->where('assignment_type', 'recurring')
            ->where(function ($query) use ($date) {
                $query->whereNull('recurring_start_date')
                      ->orWhereDate('recurring_start_date', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('recurring_end_date')
                      ->orWhereDate('recurring_end_date', '>=', $date->toDateString());
            })
            ->get()
            // Filter JSON array manually to ensure day exists
            ->filter(function ($assignment) use ($dayName) {
                return in_array($dayName, $assignment->recurring_days ?? []);
            })
            ->first();

        if ($recurringAssignment) {
            return $recurringAssignment->shift;
        }

        return null; // No shift assigned for this specific day
    }

    /**
     * Calculates the exact expected start and end datetimes for a user's shift on a given date.
     */
    public function getExpectedTimesForUserOnDate(User $user, Carbon $date): ?array
    {
        $shift = $this->getShiftForUserOnDate($user, $date);

        if (!$shift) {
            return null;
        }

        // Shift start/end are wall-clock times in the EMPLOYEE's timezone. Parse them
        // in that timezone so the resulting instants line up with the (canonically
        // stored) clock-in/out — otherwise on a UTC server an 18:00 finish is read as
        // 18:00 UTC and every on-time clock-out looks like an early departure.
        $tz = app(\App\Services\TimezoneService::class)->getEffectiveTimezone($user);

        $expectedStart = Carbon::parse($date->toDateString() . ' ' . $shift->start_time, $tz);

        $expectedEnd = Carbon::parse($date->toDateString() . ' ' . $shift->end_time, $tz);

        // If the shift crosses midnight (e.g. 22:00 to 06:00), the end time is on the next day
        if ($shift->crosses_midnight) {
            $expectedEnd->addDay();
        }

        return [
            'shift' => $shift,
            'start' => $expectedStart,
            'end' => $expectedEnd,
            'break_minutes' => $shift->break_minutes
        ];
    }

    /**
     * Safely assigns a new shift to a user.
     * If assigning a recurring shift, it implicitly closes the previous open recurring shift.
     */
    public function assignShift(User $user, Shift $shift, array $params): ShiftAssignment
    {
        $type = $params['assignment_type'] ?? 'recurring';
        
        if ($type === 'recurring') {
            // Close any existing open recurring assignment as of yesterday
            $user->shiftAssignments()
                ->where('assignment_type', 'recurring')
                ->whereNull('recurring_end_date')
                ->update(['recurring_end_date' => now()->subDay()->toDateString()]);
        }

        return ShiftAssignment::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'assigned_by' => auth()->id() ?? null,
            'assignment_type' => $type,
            'date' => $params['date'] ?? null,
            'recurring_start_date' => $params['recurring_start_date'] ?? now()->toDateString(),
            'recurring_end_date' => $params['recurring_end_date'] ?? null,
            'recurring_days' => $params['recurring_days'] ?? ["Mon","Tue","Wed","Thu","Fri"],
            'notes' => $params['notes'] ?? null
        ]);
    }
}
