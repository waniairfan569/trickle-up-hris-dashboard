<?php

namespace Database\Seeders;

use App\Models\CompanyForm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IssueReportingFormSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))->first();

        $form = CompanyForm::firstOrCreate(
            ['slug' => 'trickle-up-issue-reporting-form'],
            [
                'title' => 'Trickle Up - Issue Reporting form',
                'description' => "This form is intended for employees who wish to report a matter or request a private discussion with someone. All submissions will be handled professionally and with confidentiality. If you need further assistance, you are always welcome to have a face to face with our People Team.",
                'status' => 'active',
                'is_anonymous' => true,
                'allow_multiple_submissions' => true,
                'show_progress_bar' => false,
                'requires_signature' => false,
                'created_by' => $admin?->id,
            ]
        );

        // Only seed fields once.
        if ($form->fields()->exists()) {
            return;
        }

        $fields = [
            [
                'type' => 'text',
                'label' => 'Your name',
                'help_text' => "You may leave this blank if you'd prefer to remain anonymous.",
                'is_required' => false,
            ],
            [
                'type' => 'multi_select',
                'label' => 'Your department',
                'help_text' => 'Can be blank if you want to remain anonymous.',
                'is_required' => false,
                'options' => ['Outsourced HR team', 'Trickle Up internal team', 'Marketing Team'],
            ],
            [
                'type' => 'multi_select',
                'label' => 'Type of Concern or Topic',
                'is_required' => true,
                'options' => [
                    'Workplace conflict', 'Discrimination or harassment', 'Workload', 'Report a concern',
                    'Policy clarification', 'Pay or compensation issue', 'Promotion or career development',
                    'Personal/family issue affecting work', 'Suggestions for improvement', 'Other',
                ],
            ],
            [
                'type' => 'textarea',
                'label' => 'Who or what is involved in this matter',
                'help_text' => 'Include names, teams, or departments if you feel comfortable.',
                'is_required' => false,
            ],
            [
                'type' => 'textarea',
                'label' => 'Please describe the situation in detail',
                'is_required' => true,
            ],
            [
                'type' => 'multi_select',
                'label' => 'How has this affected you or your work environment?',
                'is_required' => false,
                'options' => [
                    "It's affecting my mental well-being", "I'm feeling uncomfortable or unsafe",
                    'My performance has declined', "It's causing tension with others",
                    "It's disrupting team productivity", "It's creating confusion about my role", 'Other',
                ],
            ],
            [
                'type' => 'multi_select',
                'label' => 'Have you tried addressing this yourself?',
                'is_required' => false,
                'options' => [
                    'Yes – I spoke to the person/people involved', 'Yes – I spoke to my manager/supervisor',
                    "No – I don't feel comfortable", "No – I wasn't sure how to handle it", 'Other',
                ],
            ],
            [
                'type' => 'multi_select',
                'label' => 'What kind of response or support are you hoping to receive?',
                'is_required' => false,
                'options' => [
                    'A private meeting to talk', 'Mediation or conflict resolution', 'Formal investigation',
                    'Clarification on company policy', 'Anonymous reporting support',
                    'Mental health or counselling referral', 'Change in role or responsibilities',
                    'I just wanted HR to be aware', 'Other',
                ],
            ],
            [
                'type' => 'multi_select',
                'label' => 'How urgent is this matter?',
                'is_required' => false,
                'options' => [
                    'Very urgent – I need support immediately',
                    "Moderately urgent – I'd like a response within a few days",
                    'Not urgent – This can be addressed when possible',
                    'Just informational – No action needed now',
                ],
            ],
            [
                'type' => 'textarea',
                'label' => 'Is there anything else you would like to share for further clarification?',
                'is_required' => false,
            ],
        ];

        $used = [];
        foreach ($fields as $i => $f) {
            $base = Str::slug($f['label'], '_') ?: 'field';
            $base = substr($base, 0, 50);
            $key = $base;
            $n = 1;
            while (in_array($key, $used, true)) {
                $key = $base . '_' . (++$n);
            }
            $used[] = $key;

            $form->fields()->create([
                'label' => $f['label'],
                'field_key' => $key,
                'type' => $f['type'],
                'help_text' => $f['help_text'] ?? null,
                'options' => $f['options'] ?? null,
                'is_required' => $f['is_required'] ?? false,
                'width' => 'full',
                'sort_order' => $i + 1,
            ]);
        }
    }
}
