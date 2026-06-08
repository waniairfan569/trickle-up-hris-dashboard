<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\TimeOffRequest;
use App\Models\PublicHoliday;
use App\Models\AttendanceRecord;
use App\Models\WorkSchedule;
use App\Models\User;
use App\Services\NotificationService;

class HRPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $user     = auth()->user()->load('role');
        $roleName = optional($user->role)->name ?? '';
        $employee = Employee::where('user_id', $user->id)
            ->with(['department', 'location'])
            ->firstOrFail();

        $year = now()->year;

        // Always: own leave balance
        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_year', $year)
            ->firstOrCreate(
                ['employee_id' => $employee->id, 'leave_year' => $year],
                ['company_id'  => $employee->company_id]
            );

        // Always: own upcoming time-off
        $upcoming = TimeOffRequest::where('employee_id', $employee->id)
            ->where('start_date', '>=', today())
            ->whereIn('status', ['pending','approved'])
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        // Always: own recent time-off history
        $history = TimeOffRequest::where('employee_id', $employee->id)
            ->where('end_date', '<', today())
            ->orderByDesc('start_date')
            ->limit(10)
            ->get();

        // Always: who's on leave today (own team)
        $teamOnLeave = $this->getTeamOnLeave($employee, $roleName);

        // Always: who's clocked in (own team)
        $teamAttendance = $this->getTeamAttendance($employee, $roleName);

        // Always: upcoming holidays
        $holidays = PublicHoliday::where('company_id', $employee->company_id)
            ->where('date', '>=', today())
            ->where('date', '<=', today()->addMonths(3))
            ->orderBy('date')
            ->get();

        // Manager+ : pending approvals for their team
        $pendingApprovals = [];
        if (in_array(strtolower($roleName), ['super admin','recruiting admin','admin']) || $this->isManager($employee)) {
            $pendingApprovals = $this->getPendingApprovals($employee, $roleName);
        }

        // Super Admin: company-wide stats
        $companyStats = null;
        if (strtolower($roleName) === 'super admin') {
            $companyStats = $this->getCompanyStats($employee->company_id);
        }

        // Today's attendance record for clock-in/out widget
        $todayAttendance = AttendanceRecord::where('employee_id', $employee->id)
            ->where('work_date', today())
            ->first();

        $company = \App\Models\Company::find($employee->company_id);

        return response()->json([
            'employee'          => collect($employee)->merge([
                'full_name' => $employee->first_name . ' ' . $employee->last_name,
                'initials'  => strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1))
            ]),
            'balance'           => $balance->append([
                'annual_remaining','sick_remaining','birthday_remaining',
                'parental_remaining','comp_off_remaining','total_remaining',
            ]),
            'upcoming_time_off' => $upcoming,
            'time_off_history'  => $history,
            'team_on_leave'     => $teamOnLeave,
            'team_attendance'   => $teamAttendance,
            'holidays'          => $holidays,
            'pending_approvals' => $pendingApprovals,
            'company_stats'     => $companyStats,
            'accessible_modules'=> $this->getAccessibleModules($roleName),
            'user'              => array_merge($user->only(['id','first_name','last_name','email','role_id','status','avatar_url','job_title']), ['role' => $user->role]),
            'today_attendance'  => $todayAttendance,
            'leave_unit'        => $company->leave_unit ?? 'days',
        ]);
    }

    private function getAccessibleModules(string $roleName): array
    {
        $all = [
            'leave_balances'     => true,
            'request_time_off'   => true,
            'team_calendar'      => true,
            'whos_in_out'        => true,
            'public_holidays'    => true,
            'own_timesheet'      => true,
            'own_profile'        => true,
            'team_leave'         => false,
            'approve_leave'      => false,
            'all_timesheets'     => false,
            'attendance_board'   => false,
            'company_reports'    => false,
            'manage_balances'    => false,
            'create_on_behalf'   => false,
            'directory'          => true,
        ];

        $role = strtolower($roleName);
        if ($role === 'super admin') return array_map(fn() => true, $all);
        if (in_array($role, ['admin', 'recruiting admin'])) return array_merge($all, [
            'team_leave' => true, 'approve_leave' => true,
            'all_timesheets' => true, 'attendance_board' => true,
        ]);
        if (in_array($role, ['hiring manager', 'manager'])) return array_merge($all, [
            'team_leave' => true, 'approve_leave' => true,
        ]);
        return $all;
    }

    public function leaveBalance(Request $request)
    {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();
        $year     = $request->year ?? now()->year;

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_year', $year)
            ->firstOrCreate(
                ['employee_id' => $employee->id, 'leave_year' => $year],
                ['company_id'  => $employee->company_id]
            );

        $requests = TimeOffRequest::where('employee_id', $employee->id)
            ->whereYear('start_date', $year)
            ->orderByDesc('start_date')
            ->get();

        $planned   = $requests->whereIn('type', ['annual','parental','comp_off']);
        $unplanned = $requests->whereIn('type', ['sick','unpaid']);
        $special   = $requests->where('type', 'birthday');

        return response()->json([
            'balance'          => $balance->append([
                'annual_remaining','sick_remaining','birthday_remaining',
                'parental_remaining','comp_off_remaining','total_remaining'
            ]),
            'planned_leaves'   => $planned->values(),
            'unplanned_leaves' => $unplanned->values(),
            'special_leaves'   => $special->values(),
            'all_requests'     => $requests->values(),
        ]);
    }

    public function requestTimeOff(Request $request)
    {
        $employee   = Employee::where('user_id', auth()->id())->firstOrFail();
        $company    = \App\Models\Company::find($employee->company_id);
        $leaveUnit  = $company->leave_unit ?? 'days';

        if ($leaveUnit === 'hours') {
            $validated = $request->validate([
                'type'             => 'required|in:annual,sick,unpaid,birthday,parental,comp_off,other',
                'start_date'       => 'required|date|after_or_equal:today',
                'hours_requested'  => 'required|numeric|min:0.5|max:744',
                'reason'           => 'nullable|string|max:1000',
                'covering_employee'=> 'nullable|string|max:255',
            ]);
            // Store hours in days_count field; end_date same as start_date for hourly
            $days = (float) $validated['hours_requested'];
            $validated['end_date'] = $validated['start_date'];
            $validated['half_day'] = false;
            $validated['half_day_period'] = null;
        } else {
            $validated = $request->validate([
                'type'             => 'required|in:annual,sick,unpaid,birthday,parental,comp_off,other',
                'start_date'       => 'required|date|after_or_equal:today',
                'end_date'         => 'required|date|after_or_equal:start_date',
                'reason'           => 'nullable|string|max:1000',
                'half_day'         => 'nullable|boolean',
                'half_day_period'  => 'nullable|in:morning,afternoon',
                'covering_employee'=> 'nullable|string|max:255',
            ]);

            $days = $this->countBusinessDays(
                $validated['start_date'],
                $validated['end_date'],
                $employee->company_id
            );

            if ($validated['half_day'] ?? false) $days = 0.5;
        }

        $balance  = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_year', now()->year)->firstOrFail();

        $remaining = match($validated['type']) {
            'annual'   => $balance->annual_remaining,
            'sick'     => $balance->sick_remaining,
            'birthday' => $balance->birthday_remaining,
            'parental' => $balance->parental_remaining,
            'comp_off' => $balance->comp_off_remaining,
            default    => 999,
        };

        if ($remaining < $days && !in_array($validated['type'], ['unpaid','other'])) {
            return response()->json([
                'message' => "Insufficient {$validated['type']} leave balance. You have {$remaining} days remaining.",
                'insufficient_balance' => true,
            ], 422);
        }

        $approver = User::find($employee->manager_id)
            ?? User::where('users.company_id', $employee->company_id)
                   ->join('roles', 'users.role_id', '=', 'roles.id')
                   ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(roles.name)'), ['super admin','admin'])
                   ->first();

        $req = TimeOffRequest::create(array_merge($validated, [
            'employee_id'   => $employee->id,
            'company_id'    => $employee->company_id,
            'approver_id'   => $approver?->id,
            'days_count'    => $days,
            'category'      => TimeOffRequest::CATEGORY_MAP[$validated['type']] ?? 'planned',
            'status'        => 'pending',
        ]));

        if (in_array($validated['type'], ['annual','parental','comp_off'])) {
            $balance->increment('annual_pending', $validated['type'] === 'annual' ? $days : 0);
        }

        // Notify admins who can approve time-off
        $empName  = $employee->first_name . ' ' . $employee->last_name;
        $typeLabel = ucfirst(str_replace('_', ' ', $validated['type']));
        $admins = NotificationService::usersWithPermission($employee->company_id, 'approve_timeoff');
        NotificationService::send(
            $admins,
            'time_off_requested',
            'New Leave Request',
            "{$empName} requested {$days}d of {$typeLabel} leave.",
            ['time_off_request_id' => $req->id, 'employee_id' => $employee->id],
            $employee->company_id
        );

        return response()->json($req->load('employee'), 201);
    }

    public function approveTimeOff($id)
    {
        $req = TimeOffRequest::with('employee')->findOrFail($id);

        $req->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approver_id' => auth()->id(),
        ]);

        $balance = LeaveBalance::where('employee_id', $req->employee_id)
            ->where('leave_year', $req->start_date->year)->first();

        if ($balance) {
            match($req->type) {
                'annual'   => $balance->increment('annual_used', $req->days_count),
                'sick'     => $balance->increment('sick_used', $req->days_count),
                'unpaid'   => $balance->increment('unpaid_used', $req->days_count),
                'birthday' => $balance->increment('birthday_used', $req->days_count),
                'parental' => $balance->increment('parental_used', $req->days_count),
                'comp_off' => $balance->increment('comp_off_used', $req->days_count),
                default    => null,
            };
            if ($req->type === 'annual') {
                $balance->decrement('annual_pending', $req->days_count);
            }
        }

        // Notify the employee their leave was approved
        if ($req->employee?->user_id) {
            $typeLabel = ucfirst(str_replace('_', ' ', $req->type));
            NotificationService::send(
                $req->employee->user_id,
                'time_off_approved',
                'Leave Request Approved',
                "Your {$typeLabel} leave request ({$req->days_count}d) has been approved.",
                ['time_off_request_id' => $req->id],
                $req->employee->company_id
            );
        }

        return response()->json($req->fresh());
    }

    public function rejectTimeOff(Request $request, $id)
    {
        $req = TimeOffRequest::with('employee')->findOrFail($id);

        $req->update([
            'status'      => 'rejected',
            'rejected_at' => now(),
            'approver_id' => auth()->id(),
            'rejection_reason' => $request->reason,
        ]);

        $balance = LeaveBalance::where('employee_id', $req->employee_id)
            ->where('leave_year', $req->start_date->year)->first();

        if ($balance && $req->type === 'annual') {
            $balance->decrement('annual_pending', $req->days_count);
        }

        // Notify the employee their leave was rejected
        if ($req->employee?->user_id) {
            $typeLabel = ucfirst(str_replace('_', ' ', $req->type));
            NotificationService::send(
                $req->employee->user_id,
                'time_off_rejected',
                'Leave Request Rejected',
                "Your {$typeLabel} leave request ({$req->days_count}d) was rejected." . ($request->reason ? " Reason: {$request->reason}" : ''),
                ['time_off_request_id' => $req->id],
                $req->employee->company_id
            );
        }

        return response()->json($req->fresh());
    }

    public function teamCalendar(Request $request)
    {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();
        $user     = auth()->user();
        $month    = $request->month ?? now()->month;
        $year     = $request->year  ?? now()->year;

        $start = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $query = TimeOffRequest::with(['employee:id,first_name,last_name,department_id'])
            ->where(function($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end]);
            })
            ->whereIn('status', ['approved','pending']);

        $user     = auth()->user()->load('role');
        $roleName = optional($user->role)->name ?? '';
        if (!in_array(strtolower($roleName), ['super admin','admin','recruiting admin'])) {
            $deptId = $employee->department_id;
            $query->whereHas('employee', fn($q) => $q->where('department_id', $deptId));
        }

        $leaves   = $query->get();
        $holidays = PublicHoliday::where('company_id', $employee->company_id)
            ->whereMonth('date', $month)->whereYear('date', $year)->get();

        return response()->json([
            'leaves'   => $leaves,
            'holidays' => $holidays,
            'month'    => $month,
            'year'     => $year,
        ]);
    }

    public function whosIn(Request $request)
    {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();
        $user     = auth()->user();
        $today    = today();

        $scope = Employee::where('company_id', $employee->company_id)
            ->with(['user:id,first_name,last_name,avatar_url', 'department:id,name']);

        // Rule 6: show all employees including hr and other roles
        // We removed the department filter here so everyone can see everyone.
        
        $employees = $scope->get();

        $attendance = AttendanceRecord::where('work_date', $today)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->with('breaks')
            ->get()
            ->keyBy('employee_id');

        $onLeave = TimeOffRequest::where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $result = $employees->map(function ($emp) use ($attendance, $onLeave) {
            $att     = $attendance->get($emp->id);
            $leave   = $onLeave->get($emp->id);
            $status  = 'not_clocked_in';

            if ($leave)                                  $status = 'on_leave';
            elseif ($att && $att->clocked_out_at)        $status = 'clocked_out';
            elseif ($att && $att->status === 'on_break') $status = 'on_break';
            elseif ($att && $att->clocked_in_at)         $status = 'working';

            return [
                'employee'      => $emp,
                'status'        => $status,
                'clocked_in_at' => $att?->clocked_in_at,
                'elapsed_minutes'=> $att ? now()->diffInMinutes($att->clocked_in_at) : 0,
                'leave_type'    => $leave?->type_label,
            ];
        });

        return response()->json([
            'employees'       => $result,
            'clocked_in_count' => $result->where('status','working')->count(),
            'on_break_count'   => $result->where('status','on_break')->count(),
            'on_leave_count'   => $result->where('status','on_leave')->count(),
            'absent_count'     => $result->where('status','not_clocked_in')->count(),
        ]);
    }

    public function holidays(Request $request)
    {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();
        $year     = $request->year    ?? now()->year;
        $country  = $request->country ?? 'US';

        $holidays = PublicHoliday::where('company_id', $employee->company_id)
            ->where('year', $year)
            ->where('country_code', $country)
            ->orderBy('date')
            ->get();

        return response()->json($holidays);
    }

    public function timesheet(Request $request)
    {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();
        $user     = auth()->user();
        $month    = $request->month ?? now()->month;
        $year     = $request->year  ?? now()->year;

        $targetEmployeeId = $employee->id;
        $user     = auth()->user()->load('role');
        $roleName = optional($user->role)->name ?? '';
        if (in_array(strtolower($roleName), ['super admin','admin','recruiting admin']) && $request->employee_id) {
            $targetEmployeeId = $request->employee_id;
        }

        $records = AttendanceRecord::where('employee_id', $targetEmployeeId)
            ->whereMonth('work_date', $month)
            ->whereYear('work_date', $year)
            ->with('breaks')
            ->orderBy('work_date')
            ->get();

        // Load work schedule for this company (all 7 days)
        $targetEmployee = Employee::find($targetEmployeeId);
        $schedules = WorkSchedule::where('company_id', $targetEmployee->company_id)
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        // Annotate each record with schedule info and late/early status
        $records = $records->map(function ($r) use ($schedules) {
            $dow = \Carbon\Carbon::parse($r->work_date)->isoWeekday(); // 1=Mon...7=Sun
            $sched = $schedules->get($dow);
            $r->schedule = $sched ? [
                'start_time'       => substr($sched->start_time, 0, 5),
                'end_time'         => substr($sched->end_time, 0, 5),
                'expected_minutes' => $sched->expected_minutes,
                'break_minutes'    => $sched->break_minutes,
                'is_working_day'   => $sched->is_working_day,
            ] : null;

            // Late arrival check
            if ($r->clocked_in_at && $sched && $sched->is_working_day) {
                $schedStart  = \Carbon\Carbon::parse($r->work_date->format('Y-m-d') . ' ' . $sched->start_time);
                $r->is_late  = \Carbon\Carbon::parse($r->clocked_in_at)->gt($schedStart->addMinutes(5));
                $r->late_by_minutes = max(0, (int)\Carbon\Carbon::parse($r->clocked_in_at)->diffInMinutes($schedStart, false) * -1);
            } else {
                $r->is_late = false;
                $r->late_by_minutes = 0;
            }
            return $r;
        });

        $summary = [
            'total_days_worked'  => $records->where('status','completed')->count(),
            'total_minutes'      => $records->sum('worked_minutes'),
            'total_overtime'     => $records->sum('overtime_minutes'),
            'pending_review'     => $records->where('approval_status','needs_review')->count(),
            'approved'           => $records->where('approval_status','approved')->count(),
            'total_late_days'    => $records->where('is_late', true)->count(),
        ];

        return response()->json([
            'records'  => $records,
            'summary'  => $summary,
            'schedule' => $schedules->map(fn($s) => [
                'day_of_week'      => $s->day_of_week,
                'day_label'        => WorkSchedule::DAY_LABELS[$s->day_of_week],
                'start_time'       => substr($s->start_time, 0, 5),
                'end_time'         => substr($s->end_time, 0, 5),
                'expected_minutes' => $s->expected_minutes,
                'break_minutes'    => $s->break_minutes,
                'is_working_day'   => $s->is_working_day,
            ]),
            'month' => $month,
            'year'  => $year,
        ]);
    }

    public function profile(Request $request)
    {
        $user     = auth()->user();
        $employee = Employee::where('user_id', $user->id)
            ->with([
                'department:id,name',
                'location:id,name,city,country',
            ])
            ->firstOrFail();

        $manager = null;
        if ($employee->manager_id) {
            $manager = Employee::where('id', $employee->manager_id)
                ->with('user:id,first_name,last_name,email,job_title,avatar_url')
                ->first();
        }

        $team = Employee::where('company_id', $employee->company_id)
            ->where('id', '!=', $employee->id)
            ->where(function($q) use ($employee) {
                $q->where('manager_id', $employee->manager_id)
                  ->orWhere('department_id', $employee->department_id);
            })
            ->with(['user:id,first_name,last_name,avatar_url,job_title'])
            ->limit(10)
            ->get();

        $todayAttendance = AttendanceRecord::where('employee_id', $employee->id)
            ->where('work_date', today())->first();

        $currentLeave = TimeOffRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', today())
            ->where('end_date',   '>=', today())
            ->first();

        return response()->json([
            'employee'         => $employee,
            'user'             => array_merge($user->only([
                'id','first_name','last_name','email','phone',
                'role_id','status','avatar_url','job_title',
                'linkedin_url','github_url','portfolio_url',
                'skills','education','years_of_experience',
                'two_factor_enabled','last_login_at',
            ]), ['role' => $user->load('role')->role]),
            'manager'          => $manager,
            'team'             => $team,
            'today_attendance' => $todayAttendance,
            'current_leave'    => $currentLeave,
            'accessible_modules' => $this->getAccessibleModules(optional($user->role)->name ?? ''),
        ]);
    }

    /* --- Helper Methods --- */
    
    private function countBusinessDays($start, $end, $companyId) {
        $start = \Carbon\Carbon::parse($start);
        $end   = \Carbon\Carbon::parse($end);
        $days  = $start->diffInDaysFiltered(function (\Carbon\Carbon $date) {
            return !$date->isWeekend();
        }, $end) + 1; // +1 to include both ends if not weekend
        // Assume no holidays filtered here for simplicity unless requested
        return $days;
    }

    private function getTeamOnLeave($employee, $role) {
        $today = today();
        $query = TimeOffRequest::where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->with('employee:id,first_name,last_name');
        if ($employee->department_id) {
            $query->whereHas('employee', function($q) use ($employee) {
                $q->where('department_id', $employee->department_id);
            });
        }
        return $query->get();
    }

    private function getTeamAttendance($employee, $role) {
        $today = today();
        $query = AttendanceRecord::where('work_date', $today)
            ->where('status', 'working')
            ->with('employee:id,first_name,last_name');
        if ($employee->department_id) {
            $query->whereHas('employee', function($q) use ($employee) {
                $q->where('department_id', $employee->department_id);
            });
        }
        return $query->get();
    }

    private function isManager($employee) {
        return Employee::where('manager_id', $employee->id)->exists();
    }

    private function getPendingApprovals($employee, string $roleName) {
        $query = TimeOffRequest::with('employee:id,first_name,last_name,department_id')
            ->where('status', 'pending');
        
        if (!in_array(strtolower($roleName), ['super admin','admin','recruiting admin'])) {
            $query->whereHas('employee', function($q) use ($employee) {
                $q->where('manager_id', $employee->id);
            });
        }
        return $query->get();
    }

    private function getCompanyStats($companyId) {
        return [
            'total_employees' => Employee::where('company_id', $companyId)->where('status','active')->count(),
            'on_leave_today'  => TimeOffRequest::where('status', 'approved')->where('start_date', '<=', today())->where('end_date', '>=', today())->count(),
        ];
    }

    public function clockIn(Request $request) {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();

        // Find existing record for today
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->where('work_date', today())
            ->first();

        if ($record) {
            // Allow re-clock-in: previously clocked out (completed) → resume working
            // Only block if already actively clocked in
            if ($record->clocked_in_at && !$record->clocked_out_at) {
                // Already clocked in — do nothing, return current state
                return response()->json(array_merge($record->fresh()->toArray(), [
                    'already_clocked_in' => true,
                ]));
            }

            // Resume: set new session start, clear clocked_out_at, keep accumulated worked_minutes
            $record->update([
                'clocked_in_at'  => now(),   // current session start
                'clocked_out_at' => null,     // actively working again
                'status'         => 'working',
            ]);
        } else {
            // First clock-in of the day
            $record = AttendanceRecord::create([
                'employee_id'   => $employee->id,
                'company_id'    => $employee->company_id,
                'work_date'     => today(),
                'clocked_in_at' => now(),
                'status'        => 'working',
                'work_type'     => 'office',
                'worked_minutes'=> 0,
            ]);
        }

        return response()->json($record->fresh());
    }

    public function clockOut(Request $request) {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->where('work_date', today())
            ->first();

        if (!$record || !$record->clocked_in_at || $record->clocked_out_at) {
            return response()->json($record ?? null);
        }

        // Accumulate this session's minutes
        $sessionMinutes = (int) now()->diffInMinutes($record->clocked_in_at);
        $totalMinutes   = ($record->worked_minutes ?? 0) + $sessionMinutes;

        // Calculate overtime from work schedule
        $dayOfWeek = now()->isoWeekday(); // 1=Mon...7=Sun
        $schedule  = WorkSchedule::where('company_id', $employee->company_id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        $overtimeMinutes = 0;
        if ($schedule && $schedule->is_working_day && $schedule->expected_minutes > 0) {
            $overtimeMinutes = max(0, $totalMinutes - $schedule->expected_minutes);
        }

        $record->update([
            'clocked_out_at'  => now(),
            'status'          => 'completed',
            'worked_minutes'  => $totalMinutes,
            'overtime_minutes'=> $overtimeMinutes,
        ]);

        return response()->json(array_merge($record->fresh()->toArray(), [
            'schedule' => $schedule ? [
                'start_time'       => substr($schedule->start_time, 0, 5),
                'end_time'         => substr($schedule->end_time, 0, 5),
                'expected_minutes' => $schedule->expected_minutes,
                'break_minutes'    => $schedule->break_minutes,
            ] : null,
        ]));
    }

    public function getDirectory(Request $request) {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();
        $search = $request->search;
        $employees = Employee::where('company_id', $employee->company_id)
            ->with(['user:id,first_name,last_name,email,job_title,avatar_url,role', 'department:id,name', 'location:id,name'])
            ->when($search, function($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('email', 'like', "%$search%");
                  });
            })->get();

        // Filter sensitive data before sending
        return response()->json($employees->map(function($emp) {
            return [
                'id'            => $emp->id,
                'first_name'    => $emp->first_name,
                'last_name'     => $emp->last_name,
                'job_title'     => $emp->user?->job_title,
                'email'         => $emp->email,
                'department'    => $emp->department?->name,
                'location'      => $emp->location?->city ?? $emp->location?->name,
                'avatar_url'    => $emp->user?->avatar_url,
                'role'          => $emp->user?->role,
                'is_active'     => $emp->status === 'active',
            ];
        }));
    }

    /** GET /v1/portal/attendance — admin view of all attendance records */
    public function attendanceRecords(Request $request)
    {
        $user      = auth()->user()->load('role');
        $companyId = $user->company_id;

        $query = AttendanceRecord::with(['employee:id,first_name,last_name,email,department_id', 'employee.department:id,name'])
            ->whereHas('employee', fn($q) => $q->where('company_id', $companyId));

        if ($request->date) {
            $query->where('work_date', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('work_date', $request->month)->whereYear('work_date', $request->year);
        } else {
            $query->where('work_date', today());
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $records = $query->orderBy('work_date', 'desc')->orderBy('clocked_in_at', 'desc')->paginate(50);

        // Attach schedule info to each record
        $schedules = WorkSchedule::where('company_id', $companyId)->get()->keyBy('day_of_week');

        $records->getCollection()->transform(function ($r) use ($schedules) {
            $dow   = \Carbon\Carbon::parse($r->work_date)->isoWeekday();
            $sched = $schedules->get($dow);
            $r->schedule = $sched ? [
                'start_time'       => substr($sched->start_time, 0, 5),
                'end_time'         => substr($sched->end_time, 0, 5),
                'expected_minutes' => $sched->expected_minutes,
                'is_working_day'   => $sched->is_working_day,
            ] : null;
            if ($r->clocked_in_at && $sched && $sched->is_working_day) {
                $schedStart  = \Carbon\Carbon::parse($r->work_date->format('Y-m-d') . ' ' . $sched->start_time);
                $actualIn    = \Carbon\Carbon::parse($r->clocked_in_at);
                $r->is_late  = $actualIn->gt($schedStart->copy()->addMinutes(5));
                $r->late_by_minutes = $r->is_late ? max(0, $actualIn->diffInMinutes($schedStart)) : 0;
            } else {
                $r->is_late = false;
                $r->late_by_minutes = 0;
            }
            return $r;
        });

        return response()->json($records);
    }

    /** PATCH /v1/portal/attendance/{id}/approve */
    public function approveAttendance(Request $request, $id)
    {
        $record = AttendanceRecord::with('employee')->findOrFail($id);
        $record->update(['approval_status' => 'approved', 'admin_note' => $request->admin_note]);

        // Notify the employee their attendance record was approved
        if ($record->employee?->user_id) {
            $dateLabel = \Carbon\Carbon::parse($record->work_date)->format('D, d M Y');
            NotificationService::send(
                $record->employee->user_id,
                'attendance_approved',
                'Attendance Approved',
                "Your attendance record for {$dateLabel} has been approved.",
                ['attendance_record_id' => $record->id],
                $record->employee->company_id
            );
        }

        return response()->json($record->fresh()->load('employee'));
    }

    public function broadcastHoliday(Request $request) {
        $validated = $request->validate([
            'holiday_name' => 'required|string',
            'message'      => 'nullable|string'
        ]);

        event(new \App\Events\HolidayBroadcast($validated['holiday_name'], $validated['message']));

        return response()->json(['message' => 'Holiday broadcasted successfully']);
    }
}
