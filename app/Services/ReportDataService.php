<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\TimeOffBalance;
use App\Models\TimeOffRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\TimezoneService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the data payload for an on-demand employee report (attendance + leave
 * + performance). Column/relationship names match THIS codebase: job_title,
 * employee_id, manager_id, hire_date and department() live on the User; leave
 * balances carry opening_balance/accrued/carried_over/adjusted.
 */
class ReportDataService
{
    public function __construct(private TimezoneService $tz) {}

    /** Statuses that count as the employee being present/working that day. */
    private const PRESENT = ['present', 'late', 'overtime', 'early_departure'];

    /** Policy-name keywords that mark a leave as "unplanned". */
    private const UNPLANNED = ['unplanned', 'casual', 'sick', 'emergency'];

    public function getEmployeeReportData(User $employee, Carbon $startDate, Carbon $endDate, string $reportType): array
    {
        // ── Attendance ──────────────────────────────────────────────
        $records = AttendanceRecord::where('user_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get();

        // Scheduled working days in the period.
        $workingDays = $this->scheduledWorkingDays($employee, $startDate, $endDate);

        $presentDays     = $records->whereIn('status', self::PRESENT)->count();
        $absentDays      = $records->where('status', 'absent')->count();
        $lateDays        = $records->where('status', 'late')->count();
        $overtimeDays    = $records->where('status', 'overtime')->count();
        $earlyDepartures = $records->where('status', 'early_departure')->count();
        $missingClockOut = $records->where('status', 'missing_clock_out')->count();
        $onLeaveDays     = $records->where('status', 'on_leave')->count();

        $totalMinutes     = (int) $records->sum('total_minutes_worked');
        $totalLateMin     = (int) $records->sum('late_minutes');
        $totalOvertimeMin = (int) $records->sum('overtime_minutes');
        $attendanceRate   = $workingDays > 0 ? round(($presentDays / $workingDays) * 100, 1) : 0;

        $dailyBreakdown = $this->buildDailyBreakdown($records, $employee);

        // ── Leaves ──────────────────────────────────────────────────
        $leaveRequests = TimeOffRequest::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()]);
            })
            ->with('policy')
            ->get();

        $leaveByType = $leaveRequests
            ->groupBy(fn ($r) => optional($r->policy)->name ?? 'Leave')
            ->map(fn ($g) => [
                'count'    => $g->count(),
                'days'     => (float) $g->sum('days_requested'),
                'requests' => $g->map(fn ($r) => [
                    'from'   => $r->start_date->format('d M'),
                    'to'     => $r->end_date->format('d M'),
                    'days'   => (float) $r->days_requested,
                    'reason' => $r->reason ?: '—',
                ])->values()->all(),
            ])->all();

        $balances = TimeOffBalance::where('user_id', $employee->id)
            ->where('year', $endDate->year)
            ->with('policy')
            ->get()
            ->map(function ($b) {
                $allocated = (float) $b->opening_balance + (float) $b->accrued + (float) $b->carried_over + (float) $b->adjusted;
                return [
                    'policy'    => optional($b->policy)->name ?? '—',
                    'allocated' => round($allocated, 2),
                    'used'      => (float) $b->used,
                    'pending'   => (float) $b->pending,
                    'remaining' => round(max(0, $allocated - (float) $b->used - (float) $b->pending), 2),
                ];
            })->values()->all();

        // ── Monthly breakdown (yearly / mid-year / custom) ──────────
        $monthlyBreakdown = [];
        if ($reportType !== 'monthly') {
            $current = $startDate->copy()->startOfMonth();
            while ($current->lte($endDate)) {
                $mStart = $current->copy()->startOfMonth();
                $mEnd   = $current->copy()->endOfMonth();
                $mRecs  = $records->filter(fn ($r) => $r->date->betweenIncluded($mStart, $mEnd));
                $monthlyBreakdown[] = [
                    'month'    => $current->format('M Y'),
                    'present'  => $mRecs->whereIn('status', self::PRESENT)->count(),
                    'absent'   => $mRecs->where('status', 'absent')->count(),
                    'late'     => $mRecs->where('status', 'late')->count(),
                    'on_leave' => $mRecs->where('status', 'on_leave')->count(),
                    'hours'    => round($mRecs->sum('total_minutes_worked') / 60, 1),
                    'late_min' => (int) $mRecs->sum('late_minutes'),
                ];
                $current->addMonth();
            }
        }

        // ── Performance score ───────────────────────────────────────
        $score = 100;
        $score -= $absentDays * 5;
        $score -= $lateDays * 2;
        $score -= $earlyDepartures * 1;
        $score -= $missingClockOut * 1;
        $score = max(0, min(100, $score));
        $grade = match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 60 => 'Average',
            default      => 'Needs Improvement',
        };
        $gradeColor = match (true) {
            $score >= 90 => '#10B981',
            $score >= 75 => '#3B82F6',
            $score >= 60 => '#F59E0B',
            default      => '#EF4444',
        };

        return [
            'meta' => [
                'report_type'  => $reportType,
                'generated_at' => now()->format('d M Y h:i A'),
                'period_label' => $startDate->format('d M Y') . ' – ' . $endDate->format('d M Y'),
                'generated_by' => auth()->user() ? auth()->user()->full_name : 'System',
            ],
            'employee' => [
                'name'       => $employee->full_name,
                'id'         => $employee->employee_id ?: 'N/A',
                'email'      => $employee->email,
                'job_title'  => $employee->job_title ?: '—',
                'department' => optional($employee->department)->name ?? '—',
                'manager'    => $employee->manager ? $employee->manager->full_name : '—',
                'join_date'  => $employee->hire_date ? Carbon::parse($employee->hire_date)->format('d M Y') : '—',
            ],
            'period' => [
                'start'        => $startDate->format('d M Y'),
                'end'          => $endDate->format('d M Y'),
                'working_days' => $workingDays,
                'total_days'   => $startDate->diffInDays($endDate) + 1,
            ],
            'attendance' => [
                'present_days'       => $presentDays,
                'absent_days'        => $absentDays,
                'late_days'          => $lateDays,
                'overtime_days'      => $overtimeDays,
                'early_departures'   => $earlyDepartures,
                'missing_clock_out'  => $missingClockOut,
                'on_leave_days'      => $onLeaveDays,
                'total_hours'        => intdiv($totalMinutes, 60) . 'h ' . ($totalMinutes % 60) . 'm',
                'total_late_min'     => $totalLateMin,
                'total_overtime_min' => $totalOvertimeMin,
                'attendance_rate'    => $attendanceRate,
                'daily_breakdown'    => $dailyBreakdown,
            ],
            'leaves' => [
                'total_days' => (float) $leaveRequests->sum('days_requested'),
                'by_type'    => $leaveByType,
                'balances'   => $balances,
            ],
            'monthly_breakdown' => $monthlyBreakdown,
            'score' => [
                'value'       => $score,
                'grade'       => $grade,
                'grade_color' => $gradeColor,
            ],
        ];
    }

    /**
     * Consolidated one-row-per-employee summary for the "All Employees" report:
     * present / late / absent, planned vs unplanned leave, WFH and hours.
     */
    public function getSummaryData(Collection $employees, Carbon $startDate, Carbon $endDate, bool $withDaily = false): array
    {
        $rows = $employees->map(fn ($emp) => $this->summaryRow($emp, $startDate, $endDate, $withDaily))->values()->all();

        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'planned' => 0, 'unplanned' => 0, 'missing_clock_out' => 0];
        foreach ($rows as $r) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $r[$k];
            }
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function summaryRow(User $employee, Carbon $startDate, Carbon $endDate, bool $withDaily = false): array
    {
        $records = AttendanceRecord::where('user_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get();

        $present = $records->whereIn('status', self::PRESENT)->count();
        $late    = $records->where('status', 'late')->count();
        $absent  = $records->where('status', 'absent')->count();
        $missing = $records->where('status', 'missing_clock_out')->count();
        $minutes = (int) $records->sum('total_minutes_worked');
        $workingDays = $this->scheduledWorkingDays($employee, $startDate, $endDate);

        $leaveRequests = TimeOffRequest::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()]);
            })
            ->with('policy')
            ->get();

        $planned = 0.0;
        $unplanned = 0.0;
        foreach ($leaveRequests as $lv) {
            if (Str::contains(Str::lower(optional($lv->policy)->name ?? ''), self::UNPLANNED)) {
                $unplanned += (float) $lv->days_requested;
            } else {
                $planned += (float) $lv->days_requested;
            }
        }

        return [
            'name'              => $employee->full_name,
            'department'        => optional($employee->department)->name ?? '—',
            'present'           => $present,
            'late'              => $late,
            'absent'            => $absent,
            'planned'           => round($planned, 2),
            'unplanned'         => round($unplanned, 2),
            'missing_clock_out' => $missing,
            'minutes'           => $minutes,
            'working_days'      => $workingDays,
            'daily'             => $withDaily ? $this->buildDailyBreakdown($records, $employee) : [],
        ];
    }

    /** Per-day rows for an employee: date, day, local clock in/out, hours, status, late/OT. */
    private function buildDailyBreakdown($records, User $employee): array
    {
        return $records->sortBy('date')->map(fn ($r) => [
            'date'         => $r->date->format('d M Y'),
            'day'          => $r->date->format('D'),
            // Convert stored clock times into the employee's local timezone.
            'clock_in'     => $r->clock_in ? $this->tz->toUserTime($r->clock_in->copy(), $employee)->format('h:i A') : '—',
            'clock_out'    => $r->clock_out ? $this->tz->toUserTime($r->clock_out->copy(), $employee)->format('h:i A') : '—',
            'hours'        => $r->total_minutes_worked
                ? intdiv($r->total_minutes_worked, 60) . 'h ' . ($r->total_minutes_worked % 60) . 'm'
                : '—',
            'status'       => $r->status,
            'late_minutes' => (int) ($r->late_minutes ?? 0),
            'overtime_min' => (int) ($r->overtime_minutes ?? 0),
        ])->values()->all();
    }

    private function scheduledWorkingDays(User $employee, Carbon $startDate, Carbon $endDate): int
    {
        $schedule = $employee->workSchedule ?? WorkSchedule::default()->first();
        $days = 0;
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            if ($schedule && $schedule->isWorkingDay($cursor)) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }
}
