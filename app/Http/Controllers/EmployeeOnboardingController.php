<?php

namespace App\Http\Controllers;

use App\Models\EmployeeOnboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingWorkflow;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class EmployeeOnboardingController extends Controller
{
    protected $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    public function index()
    {
        $user = auth()->user() ?? User::first();
        
        $myOnboarding = EmployeeOnboarding::with(['workflow', 'tasks'])
            ->where('user_id', $user->id)
            ->first();

        $teamOnboardings = collect();
        $teamIds = $user->teamMemberIds();
        if ($teamIds->isNotEmpty()) {
            $teamOnboardings = EmployeeOnboarding::with(['employee', 'workflow'])
                ->whereIn('user_id', $teamIds->all())
                ->orderBy('started_at', 'desc')
                ->get();
        }

        $allOnboardings = collect();
        if ($user->hasRole('hr_admin') || $user->hasRole('super_admin')) {
            $allOnboardings = EmployeeOnboarding::with(['employee', 'workflow'])
                ->orderBy('started_at', 'desc')
                ->paginate(20);
        }

        // For triggering new ones
        $activeWorkflows = OnboardingWorkflow::where('is_active', true)->get();
        $availableUsers = User::where('status', 'active')->orderBy('first_name')->get();

        return view('onboarding.dashboard', compact(
            'myOnboarding', 'teamOnboardings', 'allOnboardings',
            'activeWorkflows', 'availableUsers'
        ));
    }

    public function start(Request $request)
    {
        $user = auth()->user() ?? User::first();
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'workflow_id' => 'required|exists:onboarding_workflows,id',
        ]);

        $employee = User::findOrFail($request->user_id);
        $workflow = OnboardingWorkflow::findOrFail($request->workflow_id);

        $this->onboardingService->startOnboarding($employee, $workflow, $user);

        return back()->with('success', "Onboarding started for {$employee->first_name}.");
    }

    public function show(EmployeeOnboarding $onboarding)
    {
        $user = auth()->user() ?? User::first();
        
        // Auth check: Must be the employee, their manager, or HR
        if ($onboarding->user_id !== $user->id &&
            !$user->managesUser($onboarding->employee->id) &&
            !$user->hasRole('hr_admin') &&
            !$user->hasRole('super_admin')) {
            abort(403);
        }

        $onboarding->load(['employee', 'workflow', 'tasks.template', 'tasks.assignedTo']);
        
        return view('onboarding.show', compact('onboarding', 'user'));
    }

    public function completeTask(Request $request, OnboardingTask $task)
    {
        $user = auth()->user() ?? User::first();
        
        // Auth check: Must be assigned user or HR
        if ($task->assigned_to_user_id !== $user->id && !$user->hasRole('hr_admin') && !$user->hasRole('super_admin')) {
            abort(403, 'You are not assigned to complete this task.');
        }

        $request->validate(['notes' => 'nullable|string']);

        $this->onboardingService->completeTask($task, $user, $request->notes);

        return back()->with('success', 'Task marked as completed.');
    }

    public function skipTask(Request $request, OnboardingTask $task)
    {
        $user = auth()->user() ?? User::first();
        
        if (!$user->hasRole('hr_admin') && !$user->hasRole('super_admin')) {
            abort(403, 'Only HR Admins can skip tasks.');
        }

        $this->onboardingService->skipTask($task, $user);

        return back()->with('success', 'Task skipped.');
    }
}
