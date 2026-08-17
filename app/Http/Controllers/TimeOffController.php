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

        // Must have completed the minimum service — the same rule the balance
        // eligibility (TimeOffPolicy::appliesTo) uses, so "has the leave" and
        // "may request the leave" never disagree.
        $months = $employee->monthsOfService();
        if ($months === null || $months < TimeOffPolicy::PARENTAL_MIN_SERVICE_MONTHS) {
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
            'approval_stage' => ($requiresApproval && in_array($policy->approval_type, ['both', 'manager_super'], true)) ? 'manager' : null,
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

        // 2. Approvals — every pending request THIS user may decide at its current
        // stage (managers see their team's manager-stage items; HR admins see the
        // HR-stage / HR-only items company-wide; super admins see all).
        $teamRequests = collect();
        if ($user->isManager() || $user->hasRole('hr_admin') || $user->hasRole('super_admin')) {
            $teamRequests = TimeOffRequest::with('employee', 'policy')
                ->where('status', 'pending')
                ->orderBy('start_date', 'asc')
                ->get()
                ->filter(fn ($r) => $r->policy && $this->canApproveStage($user, $r))
                ->values();
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

        // Every policy (active or not) an admin can reclassify a request into —
        // e.g. a "Planned Leaves" policy that's inactive for new applications
        // but still valid to move existing leave to.
        $movePolicies = TimeOffPolicy::orderBy('name')->get();

        // Early-return (curtailment) data. myReturns keyed by request id (newest
        // wins) drives the "Return requested/approved" state in My Requests.
        $myReturns = \App\Models\LeaveReturn::where('user_id', $user->id)
            ->orderBy('created_at')->get()->keyBy('time_off_request_id');

        // Pending early-return requests this user may decide.
        $pendingReturns = collect();
        if ($user->isManager() || $user->hasRole('hr_admin') || $user->hasRole('super_admin')) {
            $rq = \App\Models\LeaveReturn::with(['employee', 'request.policy'])
                ->where('status', 'pending')->orderBy('created_at');
            if (! $user->hasRole('hr_admin') && ! $user->hasRole('super_admin')) {
                $teamIds = method_exists($user, 'teamMemberIds') ? $user->teamMemberIds() : collect();
                $rq->whereIn('user_id', $teamIds->all() ?: [-1]);
            }
            $pendingReturns = $rq->get();
        }

        return view('time-off.index', compact(
            'myPolicies', 'myBalances', 'timeOffBalances', 'myRequests',
            'teamRequests', 'allRequests', 'allPolicies', 'movePolicies',
            'myReturns', 'pendingReturns'
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
            'approval_stage' => ($policy->requires_approval && in_array($policy->approval_type, ['both', 'manager_super'], true)) ? 'manager' : null,
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

        // Enforce WHO can approve for this policy (and, for two-stage, this stage).
        abort_unless($this->canApproveStage($user, $timeOffRequest), 403, 'You are not authorised to approve this request at this stage.');

        // Two-stage policies ("Manager then HR Admin" / "Manager then Super Admin")
        // — the manager's approval just advances the request to the second stage;
        // it stays pending until that approver signs off.
        //
        // A super admin, however, is the ultimate authority: their single click
        // finalises the request outright rather than parking it for a second
        // approval by themselves (that was the "approve twice" glitch).
        $type = $timeOffRequest->policy->approval_type;
        if (in_array($type, ['both', 'manager_super'], true)
            && $timeOffRequest->approval_stage === 'manager'
            && ! $user->hasRole('super_admin')) {
            $nextStage = $type === 'both' ? 'hr_admin' : 'super_admin';
            $who = $type === 'both' ? 'HR Admin' : 'Super Admin';
            $timeOffRequest->update(['approval_stage' => $nextStage]);
            $this->notifyApprovers($timeOffRequest); // ping the next approver
            return back()->with('success', "Approved by manager — now awaiting {$who} approval.");
        }

        // Final approval (manager-only, HR-admin-only, or the HR stage of both).
        $timeOffRequest->update([
            'status' => 'approved',
            'approval_stage' => null,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->balanceService->removePending($timeOffRequest->employee, $timeOffRequest->policy, $timeOffRequest->days_requested);
        $this->balanceService->deductBalance($timeOffRequest->employee, $timeOffRequest->policy, $timeOffRequest->days_requested);

        $this->applyLeaveToAttendance($timeOffRequest);
        $this->notifyEmployeeOfDecision($timeOffRequest, 'approved');

        return back()->with('success', 'Request approved.');
    }

    /**
     * Who may approve a request, honouring the policy's approval_type — and, for
     * the two-stage "both" flow, the current stage. Super admins can always act.
     */
    private function canApproveStage(User $user, TimeOffRequest $request): bool
    {
        if ($user->hasRole('super_admin')) {
            return true; // system owner — universal override (and the Super Admin stage)
        }

        // Employees / restricted roles can never approve.
        $type = $request->policy->approval_type;
        $stage = $request->approval_stage;

        switch ($type) {
            case 'manager':
                return $user->managesUser($request->user_id);
            case 'hr_admin':
                return $user->hasRole('hr_admin');
            case 'super_admin':
                return false; // only a super admin (handled above)
            case 'both': // Manager → HR Admin
                return $stage === 'manager'
                    ? $user->managesUser($request->user_id)
                    : $user->hasRole('hr_admin');
            case 'manager_super': // Manager → Super Admin
                return $stage === 'manager'
                    ? $user->managesUser($request->user_id)
                    : false; // super-admin stage (handled above)
        }

        return false;
    }

    public function reject(Request $request, TimeOffRequest $timeOffRequest)
    {
        $user = auth()->user() ?? User::first();

        // Only someone allowed to approve this stage may reject it.
        abort_unless($this->canApproveStage($user, $timeOffRequest), 403, 'You are not authorised to decide on this request.');

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

    /**
     * Admin: move an existing request to a different leave category (policy) —
     * e.g. Unplanned → Planned. The balance the request occupies (pending while
     * awaiting a decision, or used once approved) is shifted from the old policy
     * to the new one so both stay correct.
     */
    public function changePolicy(Request $request, TimeOffRequest $timeOffRequest)
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('super_admin') || $user->hasRole('hr_admin')), 403, 'Only HR / Super Admin can change a leave category.');

        $validated = $request->validate(['policy_id' => 'required|exists:time_off_policies,id']);
        $newPolicy = TimeOffPolicy::findOrFail($validated['policy_id']);
        $oldPolicy = $timeOffRequest->policy;

        if ((int) $newPolicy->id === (int) $timeOffRequest->policy_id) {
            return back()->with('error', 'The request is already in that category.');
        }

        $days = (float) $timeOffRequest->days_requested;
        $employee = $timeOffRequest->employee;

        // Shift the balance the request occupies from old → new policy.
        if ($timeOffRequest->status === 'pending') {
            $this->balanceService->removePending($employee, $oldPolicy, $days);
            $this->balanceService->addPending($employee, $newPolicy, $days);
        } elseif ($timeOffRequest->status === 'approved') {
            $year = Carbon::parse($timeOffRequest->start_date)->year;
            $this->balanceService->getOrCreateBalance($employee, $oldPolicy, $year)->decrement('used', $days);
            $this->balanceService->getOrCreateBalance($employee, $newPolicy, $year)->increment('used', $days);
        }
        // rejected / cancelled requests hold no balance — just re-categorise.

        $fromName = $oldPolicy->name;
        $timeOffRequest->update(['policy_id' => $newPolicy->id]);

        try {
            $employee->notify(new \App\Notifications\LeaveCategoryChanged($timeOffRequest->fresh(), $fromName, $newPolicy->name));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Leave category changed from “{$fromName}” to “{$newPolicy->name}” for {$employee->first_name}.");
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

    // ===================== Return early (leave curtailment) =====================

    /** Employee asks to return early from an approved multi-day leave. */
    public function requestReturn(Request $request, TimeOffRequest $timeOffRequest)
    {
        $user = auth()->user();
        abort_unless($timeOffRequest->user_id === $user->id, 403, 'You can only return your own leave early.');
        abort_unless($this->canReturnEarly($timeOffRequest), 422, 'This leave cannot be returned early.');

        if (\App\Models\LeaveReturn::where('time_off_request_id', $timeOffRequest->id)->where('status', 'pending')->exists()) {
            return back()->with('error', 'You already have a pending early-return request for this leave.');
        }

        $validated = $request->validate([
            'return_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        $returnDate = Carbon::parse($validated['return_date'])->startOfDay();
        $start = $timeOffRequest->start_date->copy()->startOfDay();
        $end = $timeOffRequest->end_date->copy()->startOfDay();

        // The return date is the first day back at work — any day within the
        // leave (returning on the start day means it wasn't taken at all).
        if ($returnDate->lt($start) || $returnDate->gt($end)) {
            return back()->with('error', 'Return date must be within your leave (on or before it ends).');
        }

        $daysTaken = $this->workingDaysBetween($timeOffRequest->employee, $start, $returnDate->copy()->subDay());
        $returned = max(0, round(((float) $timeOffRequest->days_requested) - $daysTaken, 2));
        if ($returned <= 0) {
            return back()->with('error', 'That return date leaves no days to credit back.');
        }

        $leaveReturn = \App\Models\LeaveReturn::create([
            'company_id' => $user->company_id,
            'time_off_request_id' => $timeOffRequest->id,
            'user_id' => $user->id,
            'return_date' => $returnDate->toDateString(),
            'days_returned' => $returned,
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
        ]);

        foreach ($this->returnApprovers() as $admin) {
            try {
                $admin->notify(new \App\Notifications\LeaveReturnRequested($leaveReturn));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Early-return request sent to HR for approval.');
    }

    /** HR/manager approves: shorten the leave, credit unused days, free attendance. */
    public function approveReturn(Request $request, \App\Models\LeaveReturn $leaveReturn)
    {
        $user = auth()->user();
        $timeOff = $leaveReturn->request;
        abort_unless($timeOff, 404);
        abort_unless($this->canApproveReturn($user, $leaveReturn), 403);

        if ($leaveReturn->status !== 'pending') {
            return back()->with('error', 'This early-return request has already been decided.');
        }

        $employee = $timeOff->employee;
        $start = $timeOff->start_date->copy()->startOfDay();
        $originalEnd = $timeOff->end_date->copy()->startOfDay();
        $returnDate = $leaveReturn->return_date->copy()->startOfDay();

        if ($returnDate->lt($start) || $returnDate->gt($originalEnd)) {
            return back()->with('error', 'The return date is no longer valid for this leave.');
        }

        $daysTaken = $this->workingDaysBetween($employee, $start, $returnDate->copy()->subDay());
        $returned = max(0, round(((float) $timeOff->days_requested) - $daysTaken, 2));

        \Illuminate\Support\Facades\DB::transaction(function () use ($timeOff, $leaveReturn, $employee, $user, $returnDate, $start, $originalEnd, $daysTaken, $returned) {
            if ($returned > 0) {
                $year = Carbon::parse($timeOff->start_date)->year;
                $balance = $this->balanceService->getOrCreateBalance($employee, $timeOff->policy, $year);
                $balance->decrement('used', $returned);
            }

            if ($daysTaken <= 0) {
                // Returning on the first day → no leave was actually taken: cancel it.
                $this->revertLeaveRange($timeOff->user_id, $start->copy(), $originalEnd->copy());
                $timeOff->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            } else {
                // Free the returned days on the attendance sheet, shorten to the last day off.
                $this->revertLeaveRange($timeOff->user_id, $returnDate->copy(), $originalEnd->copy());
                $timeOff->update([
                    'end_date' => $returnDate->copy()->subDay()->toDateString(),
                    'days_requested' => $daysTaken,
                ]);
            }

            $leaveReturn->update([
                'days_returned' => $returned,
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
        });

        try {
            $employee->notify(new \App\Notifications\LeaveReturnReviewed($leaveReturn->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Early return approved — {$returned} day(s) credited back to {$employee->first_name}.");
    }

    /** HR/manager declines the early-return request; the original leave stands. */
    public function rejectReturn(Request $request, \App\Models\LeaveReturn $leaveReturn)
    {
        $user = auth()->user();
        abort_unless($this->canApproveReturn($user, $leaveReturn), 403);

        if ($leaveReturn->status !== 'pending') {
            return back()->with('error', 'This early-return request has already been decided.');
        }

        $validated = $request->validate(['review_note' => 'nullable|string|max:500']);

        $leaveReturn->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        try {
            $leaveReturn->employee->notify(new \App\Notifications\LeaveReturnReviewed($leaveReturn->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Early-return request declined.');
    }

    /** An approved multi-day full-day leave that still has remaining days can be returned early. */
    private function canReturnEarly(TimeOffRequest $r): bool
    {
        return $r->status === 'approved'
            && $r->duration_type !== 'hourly'
            && ! $r->is_half_day
            && $r->start_date && $r->end_date
            && $r->end_date->gt($r->start_date)
            && $r->end_date->gte(today());
    }

    private function canApproveReturn(User $user, \App\Models\LeaveReturn $lr): bool
    {
        if ($user->hasRole('hr_admin') || $user->hasRole('super_admin')) {
            return true;
        }
        if ($user->isManager() && method_exists($user, 'teamMemberIds')) {
            return $user->teamMemberIds()->contains($lr->user_id);
        }

        return false;
    }

    private function returnApprovers()
    {
        return User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();
    }

    /** Working days between two dates for a user (matches how days_requested is computed). */
    private function workingDaysBetween(User $user, Carbon $start, Carbon $end): float
    {
        if ($end->lt($start)) {
            return 0.0;
        }
        $schedule = $user->workSchedule ?? \App\Models\WorkSchedule::default()->first();
        if ($schedule) {
            return (float) $schedule->countWorkingDays($start->copy(), $end->copy(), $user->id);
        }

        return (float) ($start->copy()->diffInDaysFiltered(fn (Carbon $d) => ! $d->isWeekend(), $end->copy()) + 1);
    }

    /** Undo the on-leave marking for a date range (used when a leave is shortened). */
    private function revertLeaveRange(int $userId, Carbon $from, Carbon $to): void
    {
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $record = \App\Models\AttendanceRecord::where('user_id', $userId)
                ->whereDate('date', $d->toDateString())->first();
            if ($record && $record->status === 'on_leave' && ! $record->clock_in) {
                $record->status = 'absent';
                $record->save();
            }
        }
    }

    public function teamCalendar()
    {
        $requests = TimeOffRequest::with('employee', 'policy')
            ->whereIn('status', ['approved', 'pending'])
            ->get();

        // Flatten to a calendar-friendly payload the client renders as a
        // month / week / day grid. Ranges are expanded day-by-day in JS.
        $leaves = $requests
            ->filter(fn ($r) => $r->employee && $r->start_date && $r->end_date)
            ->map(function ($r) {
                $first = $r->employee->first_name ?: '';
                $last = $r->employee->last_name ?: '';
                return [
                    'id' => $r->id,
                    'name' => trim($first . ' ' . $last) ?: 'Employee',
                    'initials' => strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: '—',
                    'policy' => $r->policy?->name ?? 'Leave',
                    'status' => $r->status,
                    'start' => $r->start_date->format('Y-m-d'),
                    'end' => $r->end_date->format('Y-m-d'),
                    'duration' => $r->duration_label,
                ];
            })
            ->sortBy('start')
            ->values();

        return view('time-off.team-calendar', compact('requests', 'leaves'));
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
            $balanceToAdd = $policy->days_per_year;

            // Maternity → married female; Paternity → married male; everything
            // else applies to everyone (single source of truth on the policy).
            $shouldAssign = $policy->appliesTo($employee);

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
                
                // Opening balance = this year's allocation — pro-rata for a mid-year
                // joiner (matches the renewal preview), full otherwise. The pivot keeps
                // the full annual rate above; only the opening allocation is pro-rated.
                $allocation = app(\App\Services\LeaveRenewalService::class)->currentAllocationFor($employee, $policy);
                $balance = $this->balanceService->getOrCreateBalance($employee, $policy, $year);
                $balance->update(['opening_balance' => $allocation]);
            }
        }

        return back()->with('success', 'Default leave policies assigned (pro-rata applied for mid-year joiners).');
    }

}
