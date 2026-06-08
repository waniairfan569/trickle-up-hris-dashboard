<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProfileTemplate;
use App\Models\ProfileSection;
use App\Models\ProfileField;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DefaultProfileTemplateSeeder extends Seeder
{
    public function run()
    {
        $template = ProfileTemplate::updateOrCreate(
            ['slug' => 'default-employee-profile'],
            [
                'name' => 'Default Employee Profile',
                'type' => 'default',
                'is_active' => true,
                'description' => 'The standard profile template assigned to all employees.',
                'sort_order' => 1
            ]
        );

        // SECTION 1: Personal information
        $section1 = ProfileSection::updateOrCreate(
            ['template_id' => $template->id, 'slug' => 'personal-info'],
            ['name' => 'Personal information', 'icon' => 'ti-user', 'sort_order' => 1]
        );

        $this->seedFields($section1->id, [
            ['key' => 'full_name', 'name' => 'Full name', 'type' => 'text', 'is_required' => true, 'is_system' => true, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'personal_email', 'name' => 'Personal email', 'type' => 'email', 'is_required' => true, 'is_system' => false, 'visibility' => 'private', 'employee_can_edit' => true],
            ['key' => 'phone_number', 'name' => 'Phone number', 'type' => 'phone', 'is_required' => false, 'is_system' => false, 'visibility' => 'private', 'employee_can_edit' => true],
            ['key' => 'date_of_birth', 'name' => 'Date of birth', 'type' => 'date', 'is_required' => false, 'is_system' => false, 'visibility' => 'private', 'employee_can_edit' => true],
            ['key' => 'gender', 'name' => 'Gender', 'type' => 'dropdown', 'options' => ['Male','Female','Non-binary','Prefer not to say'], 'is_required' => false, 'is_system' => false, 'visibility' => 'internal', 'employee_can_edit' => true],
            ['key' => 'nationality', 'name' => 'Nationality', 'type' => 'dropdown', 'options' => ['British','Pakistani','American','Indian','Other'], 'is_required' => false, 'is_system' => false, 'visibility' => 'internal', 'employee_can_edit' => true],
            ['key' => 'home_address', 'name' => 'Home address', 'type' => 'textarea', 'is_required' => false, 'is_system' => false, 'visibility' => 'private', 'employee_can_edit' => true],
            ['key' => 'profile_photo', 'name' => 'Profile photo', 'type' => 'file', 'is_required' => false, 'is_system' => false, 'visibility' => 'public', 'employee_can_edit' => true],
        ]);

        // SECTION 2: Work information
        $section2 = ProfileSection::updateOrCreate(
            ['template_id' => $template->id, 'slug' => 'work-info'],
            ['name' => 'Work information', 'icon' => 'ti-briefcase', 'sort_order' => 2]
        );

        $this->seedFields($section2->id, [
            ['key' => 'work_email', 'name' => 'Work email', 'type' => 'email', 'is_required' => true, 'is_system' => true, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'job_title', 'name' => 'Job title', 'type' => 'text', 'is_required' => true, 'is_system' => false, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'department_id', 'name' => 'Department', 'type' => 'department_lookup', 'is_required' => true, 'is_system' => true, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'manager_id', 'name' => 'Manager', 'type' => 'employee_lookup', 'is_required' => true, 'is_system' => true, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'start_date', 'name' => 'Start date', 'type' => 'date', 'is_required' => true, 'is_system' => false, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'contract_type', 'name' => 'Contract type', 'type' => 'dropdown', 'options' => ['Full-time','Part-time','Contract','Internship','Freelance'], 'is_required' => true, 'is_system' => false, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'employment_type', 'name' => 'Employment type', 'type' => 'dropdown', 'options' => ['Permanent','Fixed-term','Zero-hours'], 'is_required' => true, 'is_system' => false, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'work_location', 'name' => 'Work location', 'type' => 'dropdown', 'options' => ['Office','Remote','Hybrid'], 'is_required' => false, 'is_system' => false, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'probation_end_date', 'name' => 'Probation end date', 'type' => 'date', 'is_required' => false, 'is_system' => false, 'visibility' => 'manager', 'employee_can_edit' => false],
        ]);

        // SECTION 3: Compensation
        $section3 = ProfileSection::updateOrCreate(
            ['template_id' => $template->id, 'slug' => 'compensation'],
            ['name' => 'Compensation', 'icon' => 'ti-coin', 'sort_order' => 3]
        );

        $this->seedFields($section3->id, [
            ['key' => 'salary', 'name' => 'Salary', 'type' => 'currency', 'is_required' => true, 'is_system' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
            ['key' => 'pay_frequency', 'name' => 'Pay frequency', 'type' => 'dropdown', 'options' => ['Weekly','Bi-weekly','Monthly','Annually'], 'is_required' => true, 'is_system' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
            ['key' => 'currency', 'name' => 'Currency', 'type' => 'dropdown', 'options' => ['GBP','USD','EUR','PKR'], 'is_required' => true, 'is_system' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
            ['key' => 'salary_effective_date', 'name' => 'Effective date', 'type' => 'date', 'is_required' => false, 'is_system' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
        ]);

        // SECTION 4: Employment status
        $section4 = ProfileSection::updateOrCreate(
            ['template_id' => $template->id, 'slug' => 'employment-status'],
            ['name' => 'Employment status', 'icon' => 'ti-id-badge', 'sort_order' => 4]
        );

        $this->seedFields($section4->id, [
            ['key' => 'employee_status', 'name' => 'Employment status', 'type' => 'dropdown', 'options' => ['Active','Draft','Inactive','Offboarded'], 'is_required' => true, 'is_system' => true, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'employee_id', 'name' => 'Employee ID', 'type' => 'text', 'is_required' => false, 'is_system' => true, 'visibility' => 'public', 'employee_can_edit' => false],
            ['key' => 'end_date', 'name' => 'End date', 'type' => 'date', 'is_required' => false, 'is_system' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
        ]);

        // Assign to all users
        $userIds = User::pluck('id');
        $pivots = [];
        foreach ($userIds as $userId) {
            $pivots[] = [
                'user_id' => $userId,
                'template_id' => $template->id,
                'assigned_by' => 1, // assuming super admin exists with ID 1
                'assigned_at' => now(),
            ];
        }
        
        // Insert ignoring duplicates to safely re-run
        DB::table('employee_profile_templates')->insertOrIgnore($pivots);
    }

    private function seedFields($sectionId, $fields)
    {
        $sortOrder = 1;
        foreach ($fields as $fieldData) {
            ProfileField::updateOrCreate(
                ['key' => $fieldData['key']],
                array_merge($fieldData, [
                    'section_id' => $sectionId,
                    'sort_order' => $sortOrder++
                ])
            );
        }
    }
}
