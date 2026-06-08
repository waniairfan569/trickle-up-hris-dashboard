<?php

namespace App\Services;

use App\Models\EmployeeOnboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingWorkflow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    /**
     * Start an onboarding workflow for an employee.
     * Clones task templates into live tasks.
     */
    public function startOnboarding(User $employee, OnboardingWorkflow $workflow, ?User $triggeredBy = null): EmployeeOnboarding
    {
        return DB::transaction(function () use ($employee, $workflow, $triggeredBy) {
            $onboarding = EmployeeOnboarding::create([
                'user_id' => $employee->id,
                'workflow_id' => $workflow->id,
                'started_at' => now(),
                'triggered_by' => $triggeredBy?->id,
                'status' => 'in_progress',
            ]);

            $templates = $workflow->taskTemplates;
            $startDate = $employee->hire_date ? Carbon::parse($employee->hire_date) : Carbon::today();
            $hrAdmin = User::whereHas('roles', fn($q) => $q->where('slug', 'hr_admin'))->first() ?? User::whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))->first() ?? $triggeredBy ?? User::first();

            foreach ($templates as $template) {
                // Determine assignee based on role
                $assignedToUserId = $employee->id; // default to employee
                if ($template->assigned_to_role === 'manager') {
                    $assignedToUserId = $employee->manager_id ?? $hrAdmin->id;
                } elseif ($template->assigned_to_role === 'hr_admin') {
                    $assignedToUserId = $hrAdmin->id;
                }

                // Calculate due date (calendar days from start)
                $dueDate = $startDate->copy()->addDays($template->due_days_from_start);

                OnboardingTask::create([
                    'employee_onboarding_id' => $onboarding->id,
                    'task_template_id' => $template->id,
                    'assigned_to_user_id' => $assignedToUserId,
                    'title' => $template->title,
                    'description' => $template->description,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                ]);
            }

            return $onboarding;
        });
    }

    /**
     * Complete a single task.
     */
    public function completeTask(OnboardingTask $task, User $completedBy, ?string $notes = null): void
    {
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $completedBy->id,
            'notes' => $notes,
        ]);

        $this->checkAndCompleteOnboarding($task->onboarding);
    }

    /**
     * Skip a single task (HR Admins only, conceptually).
     */
    public function skipTask(OnboardingTask $task, User $skippedBy): void
    {
        $task->update([
            'status' => 'skipped',
            'completed_at' => now(),
            'completed_by' => $skippedBy->id,
            'notes' => 'Task was manually skipped by Admin.',
        ]);

        $this->checkAndCompleteOnboarding($task->onboarding);
    }

    /**
     * Check if all required tasks are finished. If so, mark the entire onboarding as complete.
     */
    public function checkAndCompleteOnboarding(EmployeeOnboarding $onboarding): void
    {
        // Check for any pending required tasks
        $hasPendingRequired = $onboarding->tasks()
            ->where('status', 'pending')
            ->whereHas('template', function($q) {
                $q->where('is_required', true);
            })
            ->exists();

        if (!$hasPendingRequired && $onboarding->status !== 'completed') {
            $onboarding->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            
            // Any remaining non-required pending tasks can optionally be skipped here
            $onboarding->tasks()
                ->where('status', 'pending')
                ->update([
                    'status' => 'skipped',
                    'notes' => 'Auto-skipped as onboarding was completed.',
                    'completed_at' => now()
                ]);
        }
    }
}
