<?php

namespace App\Http\Controllers;

use App\Models\OnboardingTaskTemplate;
use App\Models\OnboardingWorkflow;
use Illuminate\Http\Request;

class OnboardingWorkflowController extends Controller
{
    public function index()
    {
        $workflows = OnboardingWorkflow::withCount(['taskTemplates', 'onboardings' => function($q) {
            $q->where('status', 'in_progress');
        }])->get();

        return view('onboarding.workflows.index', compact('workflows'));
    }

    public function create()
    {
        // Simple manual creation or modal, relying on standard UI.
        return redirect()->route('onboarding.workflows.index'); // Place holder if not needed
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:manual,auto_on_hire',
        ]);

        OnboardingWorkflow::create($validated);
        return back()->with('success', 'Workflow created.');
    }

    public function show(OnboardingWorkflow $onboarding_workflow)
    {
        $onboarding_workflow->load(['taskTemplates' => function($q) {
            $q->orderBy('sort_order');
        }]);
        
        return view('onboarding.workflows.show', compact('onboarding_workflow'));
    }

    public function update(Request $request, OnboardingWorkflow $onboarding_workflow)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:manual,auto_on_hire',
            'is_active' => 'boolean',
        ]);

        $onboarding_workflow->update($validated);
        return back()->with('success', 'Workflow updated.');
    }

    public function destroy(OnboardingWorkflow $onboarding_workflow)
    {
        $onboarding_workflow->delete();
        return redirect()->route('onboarding.workflows.index')->with('success', 'Workflow deleted.');
    }

    // --- Task Template Management ---

    public function storeTask(Request $request, OnboardingWorkflow $onboarding_workflow)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_role' => 'required|in:employee,manager,hr_admin',
            'due_days_from_start' => 'required|integer|min:0',
            'is_required' => 'boolean',
        ]);

        $validated['is_required'] = $request->has('is_required');
        $validated['sort_order'] = $onboarding_workflow->taskTemplates()->max('sort_order') + 1;

        $onboarding_workflow->taskTemplates()->create($validated);
        return back()->with('success', 'Task added to workflow.');
    }

    public function destroyTask(OnboardingWorkflow $onboarding_workflow, OnboardingTaskTemplate $task)
    {
        $task->delete();
        return back()->with('success', 'Task removed.');
    }
}
