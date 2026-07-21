<?php

namespace App\Http\Controllers;

use App\Models\TimeOffPolicy;
use App\Models\TimeOffRequest;
use App\Models\User;
use App\Services\TimeOffBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\TimeOffRequestSubmitted;
use App\Mail\TimeOffRequestApproved;
use App\Mail\TimeOffRequestRejected;

class TimeOffController extends Controller
{
    protected $balanceService;

    public function __construct(TimeOffBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    /**
     * Notify everyone who can act on a pending request: the employee's manager
     * AND every HR / super admin (deduped, minus the requester). Previously only
     * a single resolved approver was notified, so other admins saw nothing.
     */
    private function notifyApprovers(TimeOffRequest $request): void
    {
        if (!class_exists(\App\Notifications\TimeOffRequestSubmitted::class)) {
            return;
        }

        $approvers = collect();

        // Notify every manager of this employee — primary + any additional.
        if ($request->employee) {
            $managerIds = $request->employee->allManagerIds()->all();
            if (!empty($managerIds)) {
                $approvers = $approvers->concat(User::whereIn('id', $managerIds)->get());
            }
        }

        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();

        $approvers = $approvers->concat($admins)
            ->unique('id')
            ->reject(fn ($u) => $u->id === $request->user_id);

        foreach ($approvers as $approver) {
            try {
                $approver->notify(new \App\Notifications\TimeOffRequestSubmitted($request));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** DB notification + email to the employee when their request is approved/rejected. */
    private function notifyEmployeeOfDecision(TimeOffRequest $request, string $status): void
    {
        $employee = $request->employee;
        if (!$employee) {
            return;
        }

        try {
            $employee->notify(new \App\Notifications\TimeOffRequestStatusChanged($request, $status));
        } catch (\Throwable $e) {
            report($e);
        }

        // Best-effort email (never block the decision if mail fails).
        // Strict match on status — the email must reflect the ACTUAL decision,
        // so a reject/cancel can never accidentally send an "approved" email.
        try {
            $mailable = match ($status) {
                'approved'  => new \App\Mail\TimeOffRequestApproved($request),
                'rejected'  => new \App\Mail\TimeOffRequestRejected($request),
                'cancelled' => new \App\Mail\TimeOffRequestCancelled($request),
                default     => null,
            };
            if ($mailable && $employee->email) {
                Mail::to($employee->email)->send($mailable);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Mark each leave day as "on leave" in attendance (so a day the employee
     * didn't clock in reads as On Leave, not Absent). Applies to every leave
     * type — full-day, half-day and hourly — because approved leave should
     * never show as an absence. Days they actually clocked in are left
     * untouched (half-day / hourly people who worked their other hours stay
     * Present).
     */
    private function applyLeaveToAttendance(TimeOffRequest $request): void
    {
        $end = Carbon::parse($request->end_date)->startOfDay();
        for ($d = Carbon::parse($request->start_date)->startOfDay(); $d->lte($end); $d->addDay()) {
            try {
                $record = \App\Models\AttendanceRecord::findOrNewForDate($request->user_id, $d->toDateString());
                if ($record->clock_in) {
                    continue; // they worked that day — don't override
                }
                $record->status = 'on_leave';
                $record->save();
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Undo the on-leave marking when an approved leave is cancelled. */
    private function revertLeaveFromAttendance(TimeOffRequest $request): void
    {
        $end = Carbon::parse($request->end_date)->startOfDay();
        for ($d = Carbon::parse($request->start_date)->startOfDay(); $d->lte($end); $d->addDay()) {
            $record = \App\Models\AttendanceRecord::where('user_id', $request->user_id)
                ->whereDate('date', $d->toDateString())->first();
            if ($record && $record->status === 'on_leave' && !$record->clock_in) {
                $record->status = 'absent';
                $record->save();
            }
        }
    }

    /**
     * Null if allowed; otherwise an error message. Maternity / Paternity leave
     * is only for MARRIED employees with at least 1 year of service.
     */
    private function maternityPaternityError(User $employee, TimeOffPolicy $policy): ?string
    {
        $name = strtolower((string) $policy->name);
        $isMaternity = str_contains($name, 'maternity');
        $isPaternity = str_contains($name, 'paternity');
        if (!$isMaternity && !$isPaternity) {
            return null;
        }
        $label = $isMaternity ? 'Maternity' : 'Paternity';

        $marital = strtolower((string) (method_exists($employee, 'getFieldValue') ? $employee->getFieldValue('marital_status') : ''));
        if ($marital !== 'married') {
            return "{$label} leave is only available to married employees.";
        }

        // Start of service can live on a column OR a profile field. Different
        // fields can disagree (e.g. joined_at may hold an unrelated recent date
        // while hire_date is the real start), so we take the EARLIEST valid date
        // across every source — service length is measured from when they first
        // started, and a stray recent date can never shorten tenure.
        $gf = fn ($k) => method_exists($employee, 'getFieldValue') ? $employee->getFieldValue($k) : null;
        $candidates = [
            $employee->hire_date,
            $gf('hire_date'),
            $gf('start_date'),
            $gf('date_of_commencement'),
            $employee->joined_at,
        ];

        $start = null;
        foreach ($candidates as $c) {
            if ($c === null || $c === '') {
                continue;
            }
            try {
                $d = Carbon::parse($c);
            } catch (\Throwable $e) {
                continue;
            }
            if ($start === null || $d->lt($start)) {
                $start = $d;
            }
        }

        if (!$start) {
            return "{$label} leave requires at least 1 year of service.";
        }

        if (abs($start->diffInMonths(Carbon::today())) < 12) {
            return "{$label} leave requires at least 1 year of service.";
        }

        return null;
    }

    /**
     * Admin files a time-off request ON BEHALF of an employee (from their
     * profile). It is recorded as approved immediately (admin authority) and
     * reflected in the employee's leave balance + attendance.
     */
    public function onBehalf(Request $request)
    {
        $auth = $request->user();
        abort_unless($auth && $auth->isAdmin(), 403);

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'policy_id' => 'required|exists:time_off_policies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'duration_type' => 'nullable|in:full_day,half_day,hourly',
            'half_day_period' => 'nullable|in:morning,afternoon',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'reason' => 'nullable|string',
        ]);

        $employee = User::findOrFail($validated['employee_id']);
        $policy = TimeOffPolicy::findOrFail($validated['policy_id']);

        if ($err = $this->maternityPaternityError($employee, $policy)) {
            return back()->withErrors([$err])->withInput();
        }

        $durationType = $validated['duration_type'] ?? 'full_day';
        if (in_array($durationType, ['half_day', 'hourly'], true) && $validated['start_date'] !== $validated['end_date']) {
            return back()->withErrors(['Half-day and hourly leave can only be for a single date.'])->withInput();
        }

        // Day-equivalent (server-authoritative).
        $hours = null;
        if ($durationType === 'hourly') {
            if (empty($validated['start_time']) || empty($validated['end_time'])) {
                return back()->withErrors(['Please provide a start and end time for hourly leave.'])->withInput();
            }
            $hours = TimeOffRequest::hoursBetween($validated['start_time'], $validated['end_time']);
            if ($hours <= 0) {
                return back()->withErrors(['End time must be after start time.'])->withInput();
            }
            $days = TimeOffRequest::daysForHours($hours, $employee->id);
        } elseif ($durationType === 'half_day') {
            $days = 0.5;
        } else {
            $schedule = $employee->workSchedule ?? \App\Models\WorkSchedule::default()->first();
            $days = $schedule
                ? $schedule->countWorkingDays(Carbon::parse($validated['start_date']), Carbon::parse($validated['end_date']), $employee->id)
                : (Carbon::parse($validated['start_date'])->diffInDaysFiltered(fn ($d) => !$d->isWeekend(), Carbon::parse($validated['end_date'])) + 1);
            $days = max(0.5, (float) $days);
        }

        // Filed on behalf, but still goes through the approval flow (unless the
        // policy doesn't require approval) — the admin/manager approves it in the
        // Approvals tab, rather than it being auto-approved in one click.
        $requiresApproval = $policy->requires_approval;

        $timeOffRequest = TimeOffRequest::create([
            'user_id' => $employee->id,
            'policy_id' => $policy->id,
            'requested_by' => $auth->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_requested' => $days,
            'duration_type' => $durationType,
            'hours_requested' => $durationType === 'hourly' ? $hours : null,
            'start_time' => $durationType === 'hourly' ? $validated['start_time'] : null,
            'end_time' => $durationType === 'hourly' ? $validated['end_time'] : null,
            'is_half_day' => $durationType === 'half_day',
            'half_day_period' => $durationType === 'half_day' ? ($validated['half_day_period'] ?? null) : null,
            'reason' => $validated['reason'],
            'status' => $requiresApproval ? 'pending' : 'approved',
            'approved_by' => $requiresApproval ? null : $auth->id,
            'approved_at' => $requiresApproval ? null : now(),
        ]);

        if ($requiresApproval) {
            $this->balanceService->addPending($employee, $policy, $days);
            $this->notifyApprovers($timeOffRequest);

            return back()->with('success', 'Leave request submitted for ' . $employee->full_name . ' — now pending approval.');
        }

        $this->balanceService->deductBalance($employee, $policy, $days);
        $this->applyLeaveToAttendance($timeOffRequest);
        $this->notifyEmployeeOfDecision($timeOffRequest, 'approved');

        return back()->with('success', 'Leave added for ' . $employee->full_name . ' and approved.');
    }

    public function index(Request $request)
    {
        $user = auth()->user() ?? User::first();
        
        // 1. Employee Data
        $myPolicies = $user->timeOffPolicies()->active()->get();
        $myBalances = [];
        $year = Carbon::now()->year;
        foreach ($myPolicies as $policy) {
            $myBalances[$policy->id] = $this->balanceService->getOrCreateBalance($user, $policy, $year);
        }

        // The balance CARDS use the actual balance records for the year (the same
        // authoritative source the dashboard widget uses) so the two screens
        // always agree — not the policy_user pivot, which can drift out of sync.
        $timeOffBalances = \App\Models\TimeOffBalance::where('user_id', $user->id)
            ->where('year', $year)
            ->with('policy')
            ->get()
            ->filter(fn ($b) => $b->policy)
            ->values();
        $myRequests = TimeOffRequest::with('policy', 'approver')
            ->forUser($user)
            ->orderBy('start_date', 'desc')
            ->get();

        // 2. Manager Data (Team Requests)
        $teamRequests = collect();
        if ($user->teamMemberIds()->isNotEmpty()) {
            $teamRequests = TimeOffRequest::with('employee', 'policy')
                ->forTeam($user)
                ->where('status', 'pending')
                ->orderBy('start_date', 'asc')
                ->get();
        }

        // 3. Admin Data (All Requests)
        $allRequests = collect();
        if ($user->hasRole('hr_admin') || $user->hasRole('super_admin')) {
            $query = TimeOffRequest::with('employee', 'policy', 'approver', 'rejecter')->orderBy('created_at', 'desc');
            
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            if ($request->has('policy_id') && $request->policy_id !== 'all') {
                $query->where('policy_id', $request->policy_id);
            }
            
            $allRequests = $query->paginate(20);
        }
        
        $allPolicies = TimeOffPolicy::active()->get();

        return view('time-off.index', compact(
            'myPolicies', 'myBalances', 'timeOffBalances', 'myRequests',
            'teamRequests', 'allRequests', 'allPolicies'
        ));
    }

    public function create()
    {
        $user = auth()->user() ?? User::first();
        $year = Carbon::now()->year;

        // Offer every policy the employee can actually use: explicitly-assigned
        // active policies PLUS any they already hold a balance for this year —
        // even if that policy is inactive/archived — so every balance shown on
        // the Time-Off page can be requested against (matches the cards).
        $assignedIds = $user->timeOffPolicies()->active()->pluck('time_off_policies.id');
        $balancePolicyIds = \App\Models\TimeOffBalance::where('user_id', $user->id)
            ->where('year', $year)->pluck('policy_id');
        $ids = $assignedIds->merge($balancePolicyIds)->unique()->values();
        $myPolicies = TimeOffPolicy::whereIn('id', $ids)->get();

        $balances = [];
        foreach ($myPolicies as $policy) {
            $balances[$policy->id] = $this->balanceService->getOrCreateBalance($user, $policy, $year);
        }

        return view('time-off.create', compact('myPolicies', 'balances'));
    }

    public function store(Request $request)
    {
        $user = auth()->user() ?? User::first();
        
        $validated = $request->validate([
            'policy_id' => 'required|exists:time_off_policies,id',
            // Past dates allowed: an employee can log leave for a day already gone by.
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'duration_type' => 'nullable|in:full_day,half_day,hourly',
            'half_day_period' => 'nullable|in:morning,afternoon',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'reason' => 'required|string|max:1000',
            'days_requested' => 'nullable|numeric|min:0' // full-day: from JS; half/hourly: server computes
        ], [
            'reason.required' => 'Please provide a reason for your time-off request.',
        ]);

        $policy = TimeOffPolicy::findOrFail($validated['policy_id']);

        // Maternity / Paternity: married + at least 1 year of service.
        if ($err = $this->maternityPaternityError($user, $policy)) {
            return back()->withErrors([$err])->withInput();
        }

        $year = Carbon::parse($validated['start_date'])->year;
        $balance = $this->balanceService->getOrCreateBalance($user, $policy, $year);

        // Resolve the duration type (fall back to the legacy half-day checkbox).
        $durationType = $validated['duration_type'] ?? ($request->has('is_half_day') ? 'half_day' : 'full_day');
        $isPartial = in_array($durationType, ['half_day', 'hourly'], true);

        // Half-day / hourly reuse the policy's "allow half days" flag.
        if ($isPartial && !$policy->allow_half_days) {
            return back()->withErrors(['Partial-day (half-day / hourly) leave is not allowed for this policy.'])->withInput();
        }
        if ($isPartial && $validated['start_date'] !== $validated['end_date']) {
            return back()->withErrors(['Half-day and hourly leave can only be for a single date.'])->withInput();
        }

        // Compute the authoritative day-equivalent server-side (don't trust JS).
        $hours = null;
        if ($durationType === 'hourly') {
            if (empty($validated['start_time']) || empty($validated['end_time'])) {
                return back()->withErrors(['Please provide a start and end time for an hourly request.'])->withInput();
            }
            $hours = TimeOffRequest::hoursBetween($validated['start_time'], $validated['end_time']);
            if ($hours <= 0) {
                return back()->withErrors(['End time must be after start time.'])->withInput();
            }
            $days = TimeOffRequest::daysForHours($hours, $user->id);
        } elseif ($durationType === 'half_day') {
            $days = 0.5;
        } else {
            $days = (float) ($validated['days_requested'] ?? 0);
            if ($days < 0.5) {
                return back()->withErrors(['Please select a valid date range.'])->withInput();
            }
        }

        // Notice period only applies to FUTURE leave — backdated requests are exempt.
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        if ($startDate->gte(Carbon::today()) && $policy->min_notice_days > 0) {
            $noticeDiff = Carbon::today()->diffInDays($startDate);
            if ($noticeDiff < $policy->min_notice_days) {
                return back()->withErrors(["This policy requires at least {$policy->min_notice_days} days notice."])->withInput();
            }
        }

        if (!$policy->allow_negative_balance && $balance->remaining < $days) {
            return back()->withErrors(['Insufficient balance. You cannot request more than you have remaining.'])->withInput();
        }

        // Create Request
        $timeOffRequest = TimeOffRequest::create([
            'user_id' => $user->id,
            'policy_id' => $policy->id,
            'requested_by' => $user->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_requested' => $days,
            'duration_type' => $durationType,
            'hours_requested' => $durationType === 'hourly' ? $hours : null,
            'start_time' => $durationType === 'hourly' ? $validated['start_time'] : null,
            'end_time' => $durationType === 'hourly' ? $validated['end_time'] : null,
            'is_half_day' => $durationType === 'half_day',
            'half_day_period' => $durationType === 'half_day' ? ($validated['half_day_period'] ?? null) : null,
            'reason' => $validated['reason'],
            'status' => $policy->requires_approval ? 'pending' : 'approved',
            'approved_at' => $policy->requires_approval ? null : now(),
        ]);

        if ($policy->requires_approval) {
            $this->balanceService->addPending($user, $policy, $days);
            $this->notifyApprovers($timeOffRequest);
            return redirect()->route('time-off.index')->with('success', 'Time-off request submitted for approval.');
        } else {
            $this->balanceService->deductBalance($user, $policy, $days);
            $this->applyLeaveToAttendance($timeOffRequest);
            return redirect()->route('time-off.index')->with('success', 'Time-off request auto-approved.');
        }
    }

    public function approve(TimeOffRequest $timeOffRequest)
    {
        $user = auth()->user() ?? User::first();
        
        // Ensure user can approve
        if ($timeOffRequest->policy->approval_type === 'manager' && !$user->managesUser($timeOffRequest->employee->id)) {
            if (!$user->hasRole('hr_admin') && !$user->hasRole('super_admin')) {
                abort(403, 'Unauthorized to approve this request.');
            }
        }

        $timeOffRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->balanceService->removePending($timeOffRequest->employee, $timeOffRequest->policy, $timeOffRequest->days_requested);
        $this->balanceService->deductBalance($timeOffRequest->employee, $timeOffRequest->policy, $timeOffRequest->days_requested);

        $this->applyLeaveToAttendance($timeOffRequest);
        $this->notifyEmployeeOfDecision($timeOffRequest, 'approved');

        return back()->with('success', 'Request approved.');
    }

    public function reject(Request $request, TimeOffRequest $timeOffRequest)
    {
        $user = auth()->user() ?? User::first();
        
        $request->validate(['rejection_note' => 'required|string']);

        $timeOffRequest->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_note' => $request->rejection_note,
        ]);

        $this->balanceService->removePending($timeOffRequest->employee, $timeOffRequest->policy, $timeOffRequest->days_requested);

        $this->notifyEmployeeOfDecision($timeOffRequest, 'rejected');

        return back()->with('success', 'Request rejected.');
    }

    public function destroy(TimeOffRequest $timeOff) // Cancel
    {
        $user = auth()->user() ?? User::first();

        // Employee can only cancel own, or admin
        if ($timeOff->user_id !== $user->id && !$user->hasRole('hr_admin') && !$user->hasRole('super_admin')) {
            abort(403);
        }

        if ($timeOff->status === 'pending') {
            $this->balanceService->removePending($timeOff->employee, $timeOff->policy, $timeOff->days_requested);
        } elseif ($timeOff->status === 'approved') {
            // Restore balance if cancelled after approval
            $year = Carbon::parse($timeOff->start_date)->year;
            $balance = $this->balanceService->getOrCreateBalance($timeOff->employee, $timeOff->policy, $year);
            $balance->decrement('used', $timeOff->days_requested);
            $this->revertLeaveFromAttendance($timeOff);
        }

        $timeOff->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        // If an admin/manager cancelled someone else's request, let the
        // employee know — with the correct "cancelled" wording (not approved).
        if ($timeOff->user_id !== $user->id) {
            $this->notifyEmployeeOfDecision($timeOff, 'cancelled');
        }

        return back()->with('success', 'Request cancelled.');
    }

    public function teamCalendar()
    {
        // For demonstration, simple JSON endpoint or a full view.
        // I will return a view that renders the calendar.
        $requests = TimeOffRequest::with('employee', 'policy')
            ->whereIn('status', ['approved', 'pending'])
            ->get();
            
        return view('time-off.team-calendar', compact('requests'));
    }

    public function assignDefaultPolicies(\App\Models\User $employee)
    {
        $user = auth()->user() ?? \App\Models\User::first();

        if ($user && !$user->hasRole('super_admin') && !$user->hasRole('hr_admin')) {
            abort(403, 'Unauthorized to assign default policies.');
        }

        $policies = \App\Models\TimeOffPolicy::all();
        $year = now()->year;
        
        $hireDate = $employee->hire_date ? \Carbon\Carbon::parse($employee->hire_date) : now();
        $monthsOfService = $hireDate->diffInMonths(now());
        
        foreach ($policies as $policy) {
            $shouldAssign = false;
            $balanceToAdd = $policy->days_per_year;
            
            // Maternity → married female; Paternity → married male.
            // Casual Leave, Annual (planned) Leave and Eid Leave go to everyone.
            $isMaternity = str_contains($policy->name, 'Maternity');
            $isPaternity = str_contains($policy->name, 'Paternity');
            if ($isMaternity || $isPaternity) {
                $married = strtolower(trim((string) $employee->getFieldValue('marital_status'))) === 'married';
                $gender = strtolower(trim((string) $employee->getFieldValue('gender')));
                if ($isMaternity) {
                    $shouldAssign = $married && $gender === 'female';
                } else {
                    $shouldAssign = $married && $gender === 'male';
                }
            } else {
                $shouldAssign = true;
            }

            if ($shouldAssign) {
                // Attach the policy if not attached
                if (!$employee->timeOffPolicies()->where('time_off_policies.id', $policy->id)->exists()) {
                    $employee->timeOffPolicies()->attach($policy->id, [
                        'assigned_by' => $user ? $user->id : 1,
                        'assigned_at' => now(),
                        'custom_days_per_year' => $balanceToAdd,
                    ]);
                } else {
                    $employee->timeOffPolicies()->updateExistingPivot($policy->id, [
                        'custom_days_per_year' => $balanceToAdd,
                    ]);
                }
                
                // Set the balance
                $balance = $this->balanceService->getOrCreateBalance($employee, $policy, $year);
                // Only update opening balance if it hasn't been set or is zero, or maybe we just override it for this demo.
                // It's safe to override opening balance here since this is 'default assignment'
                $balance->update(['opening_balance' => $balanceToAdd]);
            }
        }

        return back()->with('success', 'Default leave policies assigned (Maternity → married female, Paternity → married male).');
    }

}
