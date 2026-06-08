<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeOffRequest;
use App\Models\User;
use App\Traits\LogsTimeOffAudit;
use App\Traits\LogsActivity;
use App\Events\TimeOffRequestCreated;
use App\Events\TimeOffRequestUpdated;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeOffController extends Controller
{
    use LogsTimeOffAudit, LogsActivity;

    public function index(Request $request)
    {
        $query = TimeOffRequest::with([
            'employee',
            'approvedBy:id,first_name,last_name',
            'createdByAdmin:id,first_name,last_name',
            'updatedByAdmin:id,first_name,last_name',
            'revokedBy:id,first_name,last_name',
        ]);

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'revoked') {
                $query->whereNotNull('revoked_at');
            } else {
                $query->where('status', $request->status)->whereNull('revoked_at');
            }
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->is_admin_created) {
            $query->where('is_admin_created', true);
        }

        if ($request->is_overridden) {
            $query->where('is_overridden', true);
        }

        if ($request->date_from) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('end_date', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $requests = $query->latest()->paginate(20);

        return response()->json($requests);
    }

    public function show($id)
    {
        $timeOff = TimeOffRequest::with([
            'employee.department',
            'approvedBy:id,first_name,last_name',
            'createdByAdmin:id,first_name,last_name',
            'updatedByAdmin:id,first_name,last_name',
            'revokedBy:id,first_name,last_name',
            'auditLogs.performedBy:id,first_name,last_name,role',
        ])->findOrFail($id);

        $employee = $timeOff->employee;
        $timeOff->employee_balance = [
            'annual_entitlement' => $employee->annual_leave_days ?? 20,
            'used_annual'        => $employee->used_annual_days ?? 0,
            'remaining_annual'   => $employee->remaining_annual_days ?? 20,
            'sick_entitlement'   => $employee->sick_leave_days ?? 10,
            'used_sick'          => $employee->used_sick_days ?? 0,
        ];

        return response()->json($timeOff);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'type'         => 'required|in:annual,sick,unpaid,parental,other',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'days_count'   => 'required|numeric|min:0.5|max:365',
            'reason'       => 'nullable|string|max:1000',
            'admin_note'   => 'required|string|max:1000',
            'status'       => 'nullable|in:pending,approved,rejected',
        ]);

        DB::beginTransaction();
        try {
            $timeOff = TimeOffRequest::create([
                'employee_id'      => $validated['employee_id'],
                'type'             => $validated['type'],
                'start_date'       => $validated['start_date'],
                'end_date'         => $validated['end_date'],
                'days_count'       => $validated['days_count'],
                'reason'           => $validated['reason'] ?? null,
                'admin_note'       => $validated['admin_note'],
                'status'           => $validated['status'] ?? 'pending',
                'is_admin_created' => true,
                'created_by_admin' => auth()->id(),
                'approver_id'      => ($validated['status'] === 'approved') ? auth()->id() : null,
            ]);

            if ($timeOff->status === 'approved') {
                $this->updateEmployeeBalance($timeOff->employee_id, $timeOff->type, $timeOff->days_count, 'add');
            }

            $this->logTimeOffAction(
                $timeOff->id,
                'created_on_behalf',
                null,
                $timeOff->status,
                [],
                $timeOff->toArray(),
                $validated['admin_note']
            );

            $this->logActivity('created', 'TimeOffRequest', $timeOff->id,
                "Super Admin created time-off request on behalf of employee #{$timeOff->employee_id}"
            );

            event(new TimeOffRequestCreated($timeOff->id, auth()->user()->company_id));

            DB::commit();
            return response()->json($timeOff->load('employee', 'createdByAdmin'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create request: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $timeOff = TimeOffRequest::findOrFail($id);

        $validated = $request->validate([
            'type'       => 'sometimes|in:annual,sick,unpaid,parental,other',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
            'days_count' => 'sometimes|numeric|min:0.5|max:365',
            'reason'     => 'nullable|string|max:1000',
            'admin_note' => 'required|string|max:1000',
        ]);

        $previousData = $timeOff->toArray();

        $timeOff->update(array_merge(
            collect($validated)->except('admin_note')->toArray(),
            [
                'admin_note'       => $validated['admin_note'],
                'updated_by_admin' => auth()->id(),
            ]
        ));

        $this->logTimeOffAction(
            $timeOff->id, 'edited',
            $previousData['status'], $timeOff->status,
            $previousData, $timeOff->fresh()->toArray(),
            $validated['admin_note']
        );

        $this->logActivity('updated', 'TimeOffRequest', $timeOff->id,
            "Super Admin edited time-off request #{$id}"
        );

        event(new TimeOffRequestUpdated($timeOff->id, auth()->user()->company_id));

        return response()->json($timeOff->fresh()->load('employee', 'updatedByAdmin'));
    }

    public function approve(Request $request, $id)
    {
        $timeOff = TimeOffRequest::findOrFail($id);
        $previousStatus = $timeOff->status;

        $isOverride = in_array($previousStatus, ['rejected', 'approved']);

        $validated = $request->validate([
            'override_reason' => $isOverride ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $timeOff->update([
                'status'           => 'approved',
                'approver_id'      => auth()->id(),
                'is_overridden'    => $isOverride,
                'original_status'  => $isOverride ? $previousStatus : null,
                'override_reason'  => $validated['override_reason'] ?? null,
                'updated_by_admin' => auth()->id(),
            ]);

            if ($previousStatus !== 'approved') {
                $this->updateEmployeeBalance($timeOff->employee_id, $timeOff->type, $timeOff->days_count, 'add');
            }

            $action = $isOverride ? 'force_approved' : 'approved';
            $this->logTimeOffAction(
                $timeOff->id, $action, $previousStatus, 'approved',
                [], [], $validated['override_reason'] ?? null
            );

            $this->logActivity('updated', 'TimeOffRequest', $timeOff->id,
                "Super Admin {$action} time-off request #{$id}"
            );

            // Notify the employee
            $timeOff->load('employee');
            if ($timeOff->employee?->user_id) {
                $typeLabel = ucfirst(str_replace('_', ' ', $timeOff->type));
                NotificationService::send(
                    $timeOff->employee->user_id,
                    'time_off_approved',
                    'Leave Request Approved',
                    "Your {$typeLabel} leave ({$timeOff->days_count}d) has been approved.",
                    ['time_off_request_id' => $timeOff->id],
                    $timeOff->employee->company_id
                );
            }

            DB::commit();
            return response()->json($timeOff->fresh()->load('employee', 'approvedBy'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $timeOff = TimeOffRequest::findOrFail($id);
        $previousStatus = $timeOff->status;

        $isOverride = $previousStatus === 'approved';

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'override_reason'  => $isOverride ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $timeOff->update([
                'status'           => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'is_overridden'    => $isOverride,
                'original_status'  => $isOverride ? $previousStatus : null,
                'override_reason'  => $validated['override_reason'] ?? null,
                'updated_by_admin' => auth()->id(),
            ]);

            if ($previousStatus === 'approved') {
                $this->updateEmployeeBalance($timeOff->employee_id, $timeOff->type, $timeOff->days_count, 'subtract');
            }

            $action = $isOverride ? 'force_rejected' : 'rejected';
            $this->logTimeOffAction(
                $timeOff->id, $action, $previousStatus, 'rejected',
                [], [], $validated['rejection_reason']
            );

            // Notify the employee
            $timeOff->load('employee');
            if ($timeOff->employee?->user_id) {
                $typeLabel = ucfirst(str_replace('_', ' ', $timeOff->type));
                NotificationService::send(
                    $timeOff->employee->user_id,
                    'time_off_rejected',
                    'Leave Request Rejected',
                    "Your {$typeLabel} leave ({$timeOff->days_count}d) was rejected." . ($validated['rejection_reason'] ? " Reason: {$validated['rejection_reason']}" : ''),
                    ['time_off_request_id' => $timeOff->id],
                    $timeOff->employee->company_id
                );
            }

            DB::commit();
            return response()->json($timeOff->fresh()->load('employee'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function revoke(Request $request, $id)
    {
        $timeOff = TimeOffRequest::findOrFail($id);

        if ($timeOff->status !== 'approved') {
            return response()->json(['message' => 'Only approved requests can be revoked'], 422);
        }

        $validated = $request->validate([
            'revoke_reason' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $previousStatus = $timeOff->status;

            $timeOff->update([
                'revoked_at'       => now(),
                'revoked_by'       => auth()->id(),
                'revoke_reason'    => $validated['revoke_reason'],
                'is_overridden'    => true,
                'original_status'  => $previousStatus,
                'updated_by_admin' => auth()->id(),
            ]);

            $this->updateEmployeeBalance($timeOff->employee_id, $timeOff->type, $timeOff->days_count, 'subtract');

            $this->logTimeOffAction(
                $timeOff->id, 'revoked', $previousStatus, 'revoked',
                [], [], $validated['revoke_reason']
            );

            $this->logActivity('updated', 'TimeOffRequest', $timeOff->id,
                "Super Admin revoked approved time-off request #{$id}"
            );

            DB::commit();
            return response()->json($timeOff->fresh()->load('employee', 'revokedBy'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function reopen(Request $request, $id)
    {
        $timeOff = TimeOffRequest::findOrFail($id);

        $validated = $request->validate([
            'admin_note' => 'required|string|max:1000',
        ]);

        $previousStatus = $timeOff->status;

        $timeOff->update([
            'status'           => 'pending',
            'rejection_reason' => null,
            'is_overridden'    => true,
            'original_status'  => $previousStatus,
            'updated_by_admin' => auth()->id(),
            'admin_note'       => $validated['admin_note'],
        ]);

        $this->logTimeOffAction(
            $timeOff->id, 'reopened', $previousStatus, 'pending',
            [], [], $validated['admin_note']
        );

        return response()->json($timeOff->fresh()->load('employee'));
    }

    public function cancel(Request $request, $id)
    {
        $timeOff = TimeOffRequest::findOrFail($id);

        $validated = $request->validate([
            'admin_note' => 'required|string|max:1000',
        ]);

        $previousStatus = $timeOff->status;

        $timeOff->update([
            'status'           => 'cancelled',
            'is_overridden'    => true,
            'original_status'  => $previousStatus,
            'admin_note'       => $validated['admin_note'],
            'updated_by_admin' => auth()->id(),
        ]);

        $this->logTimeOffAction(
            $timeOff->id, 'cancelled', $previousStatus, 'cancelled',
            [], [], $validated['admin_note']
        );

        return response()->json($timeOff->fresh());
    }

    public function audit($id)
    {
        $logs = \App\Models\TimeOffAuditLog::with('performedBy:id,first_name,last_name,role')
            ->where('time_off_request_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($logs);
    }

    public function employeeSummary($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $requests = TimeOffRequest::where('employee_id', $employeeId)
            ->whereNull('revoked_at')
            ->get();

        $usedByType = $requests->where('status', 'approved')
            ->groupBy('type')
            ->map(fn($g) => $g->sum('days_count'));

        return response()->json([
            'employee'         => $employee,
            'annual_entitlement' => $employee->annual_leave_days ?? 20,
            'sick_entitlement'   => $employee->sick_leave_days ?? 10,
            'used_by_type'       => $usedByType,
            'total_used'         => $requests->where('status', 'approved')->sum('days_count'),
            'pending_count'      => $requests->where('status', 'pending')->count(),
            'requests'           => $requests->load('approvedBy:id,first_name,last_name'),
        ]);
    }

    public function suspicious()
    {
        $overused = Employee::with(['timeOffRequests' => function ($q) {
            $q->where('status', 'approved')->whereNull('revoked_at');
        }])->get()->filter(function ($emp) {
            $used = $emp->timeOffRequests->where('type', 'annual')->sum('days_count');
            $entitlement = $emp->annual_leave_days ?? 20;
            return $used > ($entitlement * 0.8);
        })->map(fn($emp) => [
            'type'          => 'overuse',
            'employee'      => $emp->only(['id', 'first_name', 'last_name', 'email', 'job_title']),
            'used_days'     => $emp->timeOffRequests->where('type', 'annual')->sum('days_count'),
            'entitlement'   => $emp->annual_leave_days ?? 20,
            'percentage'    => round(($emp->timeOffRequests->where('type', 'annual')->sum('days_count') / ($emp->annual_leave_days ?? 20)) * 100),
        ])->values();

        $longRequests = TimeOffRequest::with('employee:id,first_name,last_name,email')
            ->where('days_count', '>', 10)
            ->where('status', '!=', 'cancelled')
            ->whereNull('revoked_at')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($r) => ['type' => 'long_request', 'request' => $r]);

        $revoked = TimeOffRequest::with(['employee:id,first_name,last_name', 'revokedBy:id,first_name,last_name'])
            ->whereNotNull('revoked_at')
            ->latest('revoked_at')
            ->limit(5)
            ->get()
            ->map(fn($r) => ['type' => 'revoked', 'request' => $r]);

        return response()->json([
            'overuse'       => $overused,
            'long_requests' => $longRequests,
            'revoked'       => $revoked,
            'total_flags'   => $overused->count() + $longRequests->count() + $revoked->count(),
        ]);
    }

    private function updateEmployeeBalance(int $employeeId, string $type, float $days, string $direction): void
    {
        $employee = Employee::find($employeeId);
        if (!$employee) return;

        if ($type === 'annual') {
            $current = $employee->used_annual_days ?? 0;
            $new = $direction === 'add' ? $current + $days : max(0, $current - $days);
            $employee->update([
                'used_annual_days'      => $new,
                'remaining_annual_days' => max(0, ($employee->annual_leave_days ?? 20) - $new),
            ]);
        } elseif ($type === 'sick') {
            $current = $employee->used_sick_days ?? 0;
            $new = $direction === 'add' ? $current + $days : max(0, $current - $days);
            $employee->update(['used_sick_days' => $new]);
        }
    }
}
