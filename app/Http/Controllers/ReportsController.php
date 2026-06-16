<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    /**
     * Attendance report — scheduled vs worked hours with deviation flags.
     */
    public function attendance(Request $request)
    {
        $auth = $request->user();
        abort_unless($auth && $auth->isAdmin(), 403, 'Forbidden: you do not have permission to view reports.');

        $to = $request->filled('to') ? Carbon::parse($request->get('to'))->startOfDay() : today();
        $from = $request->filled('from') ? Carbon::parse($request->get('from'))->startOfDay() : $to->copy()->subDays(13);
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $users = User::where('account_status', 'active')
            ->with(['department:id,name', 'workSchedule'])
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('employee'), fn ($q) => $q->where('id', $request->integer('employee')))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $userIds = $users->pluck('id')->all();
        $default = WorkSchedule::where('is_default', true)->first();

        // Worked minutes keyed by "userId|Y-m-d".
        $worked = AttendanceRecord::whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'date', 'total_minutes_worked'])
            ->reduce(function ($carry, $r) {
                $key = $r->user_id . '|' . Carbon::parse($r->date)->toDateString();
                $carry[$key] = ($carry[$key] ?? 0) + (int) $r->total_minutes_worked;
                return $carry;
            }, []);

        $rows = collect();
        foreach ($users as $u) {
            $schedule = $u->workSchedule ?: $default;
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $isWorkday = $schedule ? $schedule->isWorkingDay($d) : false;
                $scheduledMin = $isWorkday ? (int) round(($schedule->hours_per_day ?? 0) * 60) : 0;
                $workedMin = $worked[$u->id . '|' . $d->toDateString()] ?? 0;

                if ($scheduledMin === 0 && $workedMin === 0) {
                    continue; // skip non-working days with no activity
                }

                $deviation = $workedMin - $scheduledMin;
                $reason = ($scheduledMin > 0 && $workedMin === 0) ? 'Missing time entry' : null;

                $rows->push([
                    'date' => $d->toDateString(),
                    'name' => trim(($u->last_name ? $u->last_name . ', ' : '') . $u->first_name),
                    'employee_id' => $u->employee_id ?: $u->id,
                    'department' => optional($u->department)->name,
                    'scheduled' => $scheduledMin,
                    'worked' => $workedMin,
                    'deviation' => $deviation,
                    'reason' => $reason,
                ]);
            }
        }

        $rows = $rows->sortByDesc('date')->values();

        if ($request->get('export') === 'csv') {
            return $this->attendanceCsv($rows);
        }

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $employees = User::where('account_status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);

        return view('reports.attendance', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'departments' => $departments,
            'employees' => $employees,
            'filters' => $request->only(['department', 'employee']),
        ]);
    }

    /** Stream the attendance report as CSV. */
    private function attendanceCsv($rows)
    {
        $fmt = fn ($min) => sprintf('%dh %02dm', intdiv(abs((int) $min), 60), abs((int) $min) % 60);

        $callback = function () use ($rows, $fmt) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Employee', 'Employee ID', 'Department', 'Scheduled', 'Worked', 'Deviation', 'Deviation reason']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['date'], $r['name'], $r['employee_id'], $r['department'],
                    $fmt($r['scheduled']), $fmt($r['worked']),
                    ($r['deviation'] >= 0 ? '+' : '-') . $fmt($r['deviation']),
                    $r['reason'],
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'attendance-report.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Report Center — headcount summary + employee details.
     */
    public function index(Request $request)
    {
        $auth = $request->user();
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: you do not have permission to view reports.');
        }

        $users = User::where('account_status', '!=', 'deactivated')
            ->with(['department:id,name', 'companyEntity:id,name'])
            ->get(['id', 'first_name', 'last_name', 'email', 'job_title', 'department_id', 'company_entity_id', 'account_status', 'hire_date', 'joined_at']);

        $total = $users->count();
        $active = $users->where('account_status', 'active')->count();
        $pending = $users->where('account_status', 'invited')->count();

        $recentHires = $users->filter(function ($u) {
            $start = $u->hire_date ?? $u->joined_at;
            return $start && \Carbon\Carbon::parse($start)->gte(now()->subDays(30));
        })->count();

        $byDepartment = $users->groupBy(fn ($u) => optional($u->department)->name ?: 'Unassigned')
            ->map->count()->sortDesc();

        $byEntity = $users->groupBy(fn ($u) => optional($u->companyEntity)->name ?: 'Unassigned')
            ->map->count()->sortDesc();

        $employees = $users->sortBy(fn ($u) => $u->first_name . ' ' . $u->last_name)->values();

        return view('reports.index', compact('total', 'active', 'pending', 'recentHires', 'byDepartment', 'byEntity', 'employees'));
    }
}
