<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\TimeOffRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes the daily admin-reminder lists — used by both the reminders page and
 * the scheduled notification, so they always agree.
 */
class AdminReminders
{
    /** Employees with an approved work-from-home request covering a given day (default: tomorrow). */
    public function wfhOn(?Carbon $date = null): Collection
    {
        $day = ($date ?? Carbon::tomorrow())->toDateString();

        $userIds = TimeOffRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->whereHas('policy', fn ($q) => $q->workFromHome())
            ->pluck('user_id')->unique();

        return $this->activeEmployees($userIds);
    }

    /** Employees who clocked in late on a given day (default: today). */
    public function lateOn(?Carbon $date = null): Collection
    {
        $day = ($date ?? Carbon::today());

        $userIds = AttendanceRecord::whereDate('date', $day->toDateString())
            ->where('late_minutes', '>', 0)
            ->pluck('user_id')->unique();

        return $this->activeEmployees($userIds);
    }

    /** Active, real employees (excludes deactivated + attendance-excluded owners). */
    private function activeEmployees(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids->all())
            ->where('account_status', 'active')
            ->where('exclude_from_attendance', false)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'avatar_url']);
    }
}
