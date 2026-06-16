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
        $myRequests = TimeOffRequest::with('policy', 'approver')
            ->forUser($user)
            ->orderBy('start_date', 'desc')
            ->get();

        // 2. Manager Data (Team Requests)
        $teamRequests = collect();
        if (User::where('manager_id', $user->id)->exists()) {
            $teamRequests = TimeOffRequest::with('employee', 'policy')
                ->forTeam($user)
                ->where('status', 'pending')
                ->orderBy('start_date', 'asc')
                ->get();
        }

        // 3. Admin Data (All Requests)
        $allRequests = collect();
        if ($user->hasRole('hr_admin') || $user->hasRole('super_admin')) {
            $query = TimeOffRequest::with('employee', 'policy', 'approver')->orderBy('created_at', 'desc');
            
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
            'myPolicies', 'myBalances', 'myRequests', 
            'teamRequests', 'allRequests', 'allPolicies'
        ));
    }

    public function create()
    {
        $user = auth()->user() ?? User::first();
        $myPolicies = $user->timeOffPolicies()->active()->get();
        
        $year = Carbon::now()->year;
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
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_half_day' => 'boolean',
            'half_day_period' => 'nullable|in:morning,afternoon',
            'reason' => 'nullable|string',
            'days_requested' => 'required|numeric|min:0.5' // From JS calculator
        ]);

        $policy = TimeOffPolicy::findOrFail($validated['policy_id']);
        $year = Carbon::parse($validated['start_date'])->year;
        $balance = $this->balanceService->getOrCreateBalance($user, $policy, $year);

        // Validation Rules
        if (!$policy->allow_half_days && $request->is_half_day) {
            return back()->withErrors(['Half days are not allowed for this policy.'])->withInput();
        }
        
        if ($request->is_half_day && $validated['start_date'] !== $validated['end_date']) {
            return back()->withErrors(['Half days can only be selected for a single date.'])->withInput();
        }
        
        $noticeDiff = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($validated['start_date']));
        if ($noticeDiff < $policy->min_notice_days) {
            return back()->withErrors(["This policy requires at least {$policy->min_notice_days} days notice."])->withInput();
        }

        if (!$policy->allow_negative_balance && $balance->remaining < $validated['days_requested']) {
            return back()->withErrors(['Insufficient balance. You cannot request more days than you have remaining.'])->withInput();
        }

        // Create Request
        $timeOffRequest = TimeOffRequest::create([
            'user_id' => $user->id,
            'policy_id' => $policy->id,
            'requested_by' => $user->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_requested' => $validated['days_requested'],
            'is_half_day' => $request->has('is_half_day'),
            'half_day_period' => $validated['half_day_period'] ?? null,
            'reason' => $validated['reason'],
            'status' => $policy->requires_approval ? 'pending' : 'approved',
            'approved_at' => $policy->requires_approval ? null : now(),
        ]);

        if ($policy->requires_approval) {
            $this->balanceService->addPending($user, $policy, $validated['days_requested']);
            // Notify Approver
            if (class_exists(\App\Notifications\TimeOffRequestSubmitted::class)) {
                $superAdmin = \App\Models\User::whereHas('roles', function($q) { $q->where('name', 'super_admin')->orWhere('slug', 'super_admin'); })->first();
                $hrAdmin = \App\Models\User::whereHas('roles', function($q) { $q->where('name', 'hr_admin')->orWhere('slug', 'hr_admin'); })->first();
                
                $approver = $policy->approval_type === 'hr_admin' 
                    ? ($hrAdmin ?? $superAdmin) 
                    : (\App\Models\User::find($user->manager_id) ?? $superAdmin);
                
                if ($approver) {
                    $approver->notify(new \App\Notifications\TimeOffRequestSubmitted($timeOffRequest));
                }
            }
            return redirect()->route('time-off.index')->with('success', 'Time-off request submitted for approval.');
        } else {
            $this->balanceService->deductBalance($user, $policy, $validated['days_requested']);
            return redirect()->route('time-off.index')->with('success', 'Time-off request auto-approved.');
        }
    }

    public function approve(TimeOffRequest $timeOffRequest)
    {
        $user = auth()->user() ?? User::first();
        
        // Ensure user can approve
        if ($timeOffRequest->policy->approval_type === 'manager' && $timeOffRequest->employee->manager_id !== $user->id) {
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

        if (class_exists(\App\Notifications\TimeOffRequestStatusChanged::class)) {
            $timeOffRequest->employee->notify(new \App\Notifications\TimeOffRequestStatusChanged($timeOffRequest, 'approved'));
        }

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

        if (class_exists(\App\Notifications\TimeOffRequestStatusChanged::class)) {
            $timeOffRequest->employee->notify(new \App\Notifications\TimeOffRequestStatusChanged($timeOffRequest, 'rejected'));
        }

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
        }

        $timeOff->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

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
