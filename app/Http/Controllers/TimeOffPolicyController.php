<?php

namespace App\Http\Controllers;

use App\Models\TimeOffPolicy;
use App\Models\User;
use App\Services\TimeOffBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimeOffPolicyController extends Controller
{
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
            'approval_type' => 'required|in:manager,hr_admin,both',
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
            'approval_type' => 'required|in:manager,hr_admin,both',
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

        $this->balanceService->manualAdjust($user, $timeOffPolicy, $request->amount, $request->note ?? 'Manual adjustment', $admin);

        return back()->with('success', 'Balance adjusted successfully.');
    }
}
