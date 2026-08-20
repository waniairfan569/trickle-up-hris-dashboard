<?php

namespace App\Http\Controllers;

use App\Models\TimeOffPolicy;
use App\Models\User;
use App\Services\TimeOffBalanceService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimeOffPolicyController extends Controller
{
    use LogsActivity;

    protected $balanceService;

    public function __construct(TimeOffBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    public function index()
    {
        $policies = TimeOffPolicy::withCount('employees')->orderBy('name')->get();
        return view('time-off-policies.index', compact('policies'));
    }

    public function create()
    {
        return view('time-off-policies.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:annual,sick,unpaid,maternity,paternity,bereavement,custom',
            'accrual_type' => 'required|in:none,monthly,annually',
            'days_per_year' => 'required|numeric|min:0|max:365',
            'max_balance' => 'nullable|numeric|min:0',
            'carry_over' => 'boolean',
            'carry_over_max' => 'nullable|numeric|min:0',
            'requires_approval' => 'boolean',
            'approval_type' => 'required|in:manager,hr_admin,super_admin,both,manager_super',
            'min_notice_days' => 'required|integer|min:0',
            'allow_half_days' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'auto_assign_to_new_employees' => 'boolean',
        ]);

        // Fix boolean casts from checkboxes
        $validated['carry_over'] = $request->has('carry_over');
        $validated['requires_approval'] = $request->has('requires_approval');
        $validated['allow_half_days'] = $request->has('allow_half_days');
        $validated['allow_negative_balance'] = $request->has('allow_negative_balance');
        $validated['is_paid'] = $request->has('is_paid');
        $validated['is_active'] = $request->has('is_active');
        $validated['auto_assign_to_new_employees'] = $request->has('auto_assign_to_new_employees');
        $validated['show_on_dashboard'] = $request->has('show_on_dashboard');

        if (!$validated['carry_over']) {
            $validated['carry_over_max'] = null;
        }

        TimeOffPolicy::create($validated);

        return redirect()->route('time-off-policies.index')->with('success', 'Time-off policy created.');
    }

    public function edit(TimeOffPolicy $timeOffPolicy)
    {
        return view('time-off-policies.edit', compact('timeOffPolicy'));
    }

    public function update(Request $request, TimeOffPolicy $timeOffPolicy)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:annual,sick,unpaid,maternity,paternity,bereavement,custom',
            'accrual_type' => 'required|in:none,monthly,annually',
            'days_per_year' => 'required|numeric|min:0|max:365',
            'max_balance' => 'nullable|numeric|min:0',
            'carry_over' => 'boolean',
            'carry_over_max' => 'nullable|numeric|min:0',
            'requires_approval' => 'boolean',
            'approval_type' => 'required|in:manager,hr_admin,super_admin,both,manager_super',
            'min_notice_days' => 'required|integer|min:0',
            'allow_half_days' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'auto_assign_to_new_employees' => 'boolean',
        ]);

        $validated['carry_over'] = $request->has('carry_over');
        $validated['requires_approval'] = $request->has('requires_approval');
        $validated['allow_half_days'] = $request->has('allow_half_days');
        $validated['allow_negative_balance'] = $request->has('allow_negative_balance');
        $validated['is_paid'] = $request->has('is_paid');
        $validated['is_active'] = $request->has('is_active');
        $validated['auto_assign_to_new_employees'] = $request->has('auto_assign_to_new_employees');
        $validated['show_on_dashboard'] = $request->has('show_on_dashboard');

        if (!$validated['carry_over']) {
            $validated['carry_over_max'] = null;
        }

        $timeOffPolicy->update($validated);

        return redirect()->route('time-off-policies.index')->with('success', 'Time-off policy updated.');
    }

    public function destroy(TimeOffPolicy $timeOffPolicy)
    {
        // TODO: In a full app, check if there are any pending requests before allowing deletion
        $timeOffPolicy->delete();
        return redirect()->route('time-off-policies.index')->with('success', 'Policy deleted.');
    }

    // --- Assignment & Balances ---

    public function assign(Request $request, TimeOffPolicy $policy)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'custom_days_per_year' => 'nullable|numeric|min:0',
        ]);

        $year = Carbon::now()->year;

        foreach ($request->user_ids as $userId) {
            $policy->employees()->syncWithoutDetaching([
                $userId => [
                    'assigned_by' => auth()->id() ?? 1,
                    'custom_days_per_year' => $request->custom_days_per_year,
                    'effective_from' => now(),
                ]
            ]);

            // Create initial balance
            $user = User::find($userId);
            $this->balanceService->getOrCreateBalance($user, $policy, $year);
        }

        return back()->with('success', 'Policy assigned and balances created.');
    }

    public function unassign(Request $request, TimeOffPolicy $policy)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        // TODO: Ensure no pending requests
        $policy->employees()->detach($request->user_id);

        return back()->with('success', 'User unassigned from policy.');
    }

    /**
     * At-a-glance grid: every employee (rows) × every leave category (columns),
     * showing remaining / allocated days for the selected year.
     */
    public function balancesOverview()
    {
        $year = (int) request('year', Carbon::now()->year);

        $policies = TimeOffPolicy::orderBy('name')->get();

        // Anyone who has a balance this year OR is assigned to any policy.
        $userIds = \App\Models\TimeOffBalance::where('year', $year)->pluck('user_id')
            ->merge(User::whereHas('timeOffPolicies')->pluck('id'))
            ->unique();

        $employees = User::whereIn('id', $userIds)
            ->where('account_status', '!=', 'deactivated')
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'avatar_url', 'job_title']);

        // balances[user_id][policy_id] = TimeOffBalance
        $balances = \App\Models\TimeOffBalance::where('year', $year)->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('policy_id'));

        $years = \App\Models\TimeOffBalance::select('year')->distinct()->orderByDesc('year')->pluck('year');
        if ($years->isEmpty() || !$years->contains($year)) {
            $years = $years->push($year)->unique()->sortDesc()->values();
        }

        return view('time-off-policies.balances-overview', compact('policies', 'employees', 'balances', 'year', 'years'));
    }

    /**
     * Recompute every employee's OPENING balance for a year to the correct
     * allocation — pro-rata for mid-year joiners, full otherwise — matching the
     * renewal preview. Only touches opening_balance; used/pending/accrued/etc stay.
     */
    public function recomputeBalances(Request $request)
    {
        $year = (int) $request->input('year', Carbon::now()->year);
        $renewal = app(\App\Services\LeaveRenewalService::class);

        $balances = \App\Models\TimeOffBalance::where('year', $year)->with(['user', 'policy'])->get();
        $updated = 0;
        $removed = 0;

        foreach ($balances as $b) {
            if (!$b->user || !$b->policy) {
                continue;
            }

            // Policy the employee can't have (e.g. Maternity for a man). If nothing
            // has been taken or credited, drop the row entirely; otherwise zero it.
            if (!$b->policy->appliesTo($b->user)) {
                $isEmpty = (float) $b->used == 0 && (float) $b->pending == 0
                    && (float) $b->accrued == 0 && (float) $b->carried_over == 0 && (float) $b->adjusted == 0;
                if ($isEmpty) {
                    $b->delete();
                    $removed++;
                } elseif ((float) $b->opening_balance != 0.0) {
                    $b->opening_balance = 0;
                    $b->save();
                    $updated++;
                }
                continue;
            }

            $allocation = round($renewal->currentAllocationFor($b->user, $b->policy), 2);
            if ((float) $b->opening_balance !== $allocation) {
                $b->opening_balance = $allocation;
                $b->save();
                $updated++;
            }
        }

        $msg = "Recalculated {$updated} balance(s) — pro-rata applied for mid-year joiners.";
        if ($removed > 0) {
            $msg .= " Removed {$removed} ineligible leave(s) (e.g. maternity for men).";
        }

        return redirect()->route('time-off-policies.balances-overview', ['year' => $year])->with('success', $msg);
    }

    public function balances(TimeOffPolicy $timeOffPolicy)
    {
        $year = request('year', Carbon::now()->year);
        
        $assignedUsers = $timeOffPolicy->employees()->orderBy('first_name')->get();
        $assignedUserIds = $assignedUsers->pluck('id')->toArray();
        $availableUsers = User::whereNotIn('id', $assignedUserIds)->orderBy('first_name')->get();

        // Load balances for this year
        $balances = $timeOffPolicy->balances()->where('year', $year)->get()->keyBy('user_id');

        return view('time-off-policies.balances', compact('timeOffPolicy', 'assignedUsers', 'availableUsers', 'balances', 'year'));
    }

    public function adjustBalance(Request $request, TimeOffPolicy $timeOffPolicy)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric',
            'note' => 'nullable|string'
        ]);

        $user = User::find($request->user_id);
        $admin = auth()->user() ?? User::first();

        $amount = (float) $request->amount;
        $note = $request->note ?: 'Manual adjustment';

        $this->balanceService->manualAdjust($user, $timeOffPolicy, $amount, $note, $admin);

        // Record it in the activity feed so the change is traceable everywhere.
        // Best-effort: a broadcast/queue hiccup must never fail the adjustment.
        try {
            $sign = $amount >= 0 ? '+' : '';
            $this->logActivity(
                'adjusted',
                'TimeOffBalance',
                $user->id,
                "Adjusted {$user->full_name}'s {$timeOffPolicy->name} balance by {$sign}{$amount} day(s) — {$note}",
                ['policy_id' => $timeOffPolicy->id, 'amount' => $amount, 'note' => $note]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', ($amount >= 0 ? 'Added ' : 'Subtracted ') . abs($amount) . ' day(s) — balance updated.');
    }
}
