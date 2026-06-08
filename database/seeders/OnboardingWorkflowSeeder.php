<?php

namespace Database\Seeders;

use App\Models\OnboardingWorkflow;
use Illuminate\Database\Seeder;

class OnboardingWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workflow = OnboardingWorkflow::create([
            'name' => 'Standard Employee Onboarding',
            'description' => 'The standard checklist for all new hires across the company.',
            'trigger_type' => 'manual',
            'is_active' => true,
        ]);

        $tasks = [
            [
                'title' => 'Sign employment contract',
                'description' => 'Review and sign the official employment contract provided by HR.',
                'assigned_to_role' => 'employee',
                'due_days_from_start' => 1,
                'is_required' => true,
            ],
            [
                'title' => 'Complete personal details form',
                'description' => 'Provide emergency contacts, address, and demographic information.',
                'assigned_to_role' => 'employee',
                'due_days_from_start' => 1,
                'is_required' => true,
            ],
            [
                'title' => 'Set up work email',
                'description' => 'Create Google Workspace account and share credentials securely.',
                'assigned_to_role' => 'hr_admin',
                'due_days_from_start' => 1,
                'is_required' => true,
            ],
            [
                'title' => 'IT equipment setup',
                'description' => 'Ensure laptop and required accessories are delivered and functioning.',
                'assigned_to_role' => 'manager',
                'due_days_from_start' => 1,
                'is_required' => true,
            ],
            [
                'title' => 'Add bank details',
                'description' => 'Input routing and account numbers for payroll processing.',
                'assigned_to_role' => 'employee',
                'due_days_from_start' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Read employee handbook',
                'description' => 'Review company policies, values, and code of conduct.',
                'assigned_to_role' => 'employee',
                'due_days_from_start' => 3,
                'is_required' => true,
            ],
            [
                'title' => 'Intro meeting with manager',
                'description' => 'Initial 1-on-1 to align on first 30 days expectations.',
                'assigned_to_role' => 'manager',
                'due_days_from_start' => 3,
                'is_required' => false,
            ],
            [
                'title' => 'Complete compliance training',
                'description' => 'Complete the mandatory online security and compliance courses.',
                'assigned_to_role' => 'employee',
                'due_days_from_start' => 7,
                'is_required' => true,
            ],
        ];

        foreach ($tasks as $index => $taskData) {
            $taskData['sort_order'] = $index + 1;
            $workflow->taskTemplates()->create($taskData);
        }
    }
}
