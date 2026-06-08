<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Module: employees
            ['name' => 'View All Employees', 'slug' => 'view_all_employees', 'module' => 'employees', 'description' => 'Can view details of all employees'],
            ['name' => 'View Own Profile', 'slug' => 'view_own_profile', 'module' => 'employees', 'description' => 'Can view own profile details'],
            ['name' => 'View Team Profiles', 'slug' => 'view_team_profiles', 'module' => 'employees', 'description' => 'Can view profiles of team members'],
            ['name' => 'Create Employee', 'slug' => 'create_employee', 'module' => 'employees', 'description' => 'Can onboard and create new employee records'],
            ['name' => 'Edit Employee', 'slug' => 'edit_employee', 'module' => 'employees', 'description' => 'Can edit details of other employees'],
            ['name' => 'Delete Employee Draft', 'slug' => 'delete_employee_draft', 'module' => 'employees', 'description' => 'Can delete employee draft profiles'],
            ['name' => 'Edit Own Profile', 'slug' => 'edit_own_profile', 'module' => 'employees', 'description' => 'Can edit own profile details'],
            ['name' => 'Edit Direct Reports', 'slug' => 'edit_direct_reports', 'module' => 'employees', 'description' => 'Can edit profile details of direct reports'],

            // Module: onboarding
            ['name' => 'View Onboarding Dashboard', 'slug' => 'view_onboarding_dashboard', 'module' => 'onboarding', 'description' => 'Can view the onboarding status dashboard'],
            ['name' => 'Manage Onboarding', 'slug' => 'manage_onboarding', 'module' => 'onboarding', 'description' => 'Can set up and complete onboarding tasks'],
            ['name' => 'Start Offboarding', 'slug' => 'start_offboarding', 'module' => 'onboarding', 'description' => 'Can trigger and manage employee offboarding workflows'],

            // Module: time_off
            ['name' => 'Request Time Off', 'slug' => 'request_time_off', 'module' => 'time_off', 'description' => 'Can request and submit time off'],
            ['name' => 'Approve Time Off', 'slug' => 'approve_time_off', 'module' => 'time_off', 'description' => 'Can approve or reject team time off requests'],
            ['name' => 'Manage Time Off Policies', 'slug' => 'manage_time_off_policies', 'module' => 'time_off', 'description' => 'Can create and configure time off policy types'],
            ['name' => 'View Time Off Reports', 'slug' => 'view_time_off_reports', 'module' => 'time_off', 'description' => 'Can view time off analysis reports'],
            ['name' => 'Edit Time Off Balance', 'slug' => 'edit_time_off_balance', 'module' => 'time_off', 'description' => 'Can adjust employee time off balance sheets'],

            // Module: documents
            ['name' => 'View Company Files', 'slug' => 'view_company_files', 'module' => 'documents', 'description' => 'Can view shared company resource documents'],
            ['name' => 'Manage Company Files', 'slug' => 'manage_company_files', 'module' => 'documents', 'description' => 'Can create, edit, or delete shared files'],
            ['name' => 'Upload Own Files', 'slug' => 'upload_own_files', 'module' => 'documents', 'description' => 'Can upload files to own profile'],
            ['name' => 'Request E-signature', 'slug' => 'request_esignature', 'module' => 'documents', 'description' => 'Can send and request e-signatures on contracts'],
            ['name' => 'Track E-signature', 'slug' => 'track_esignature', 'module' => 'documents', 'description' => 'Can track signed document statuses'],

            // Module: performance
            ['name' => 'Write Self Review', 'slug' => 'write_self_review', 'module' => 'performance', 'description' => 'Can write self performance appraisals'],
            ['name' => 'Write Manager Review', 'slug' => 'write_manager_review', 'module' => 'performance', 'description' => 'Can write performance appraisals for direct reports'],
            ['name' => 'View Submitted Review', 'slug' => 'view_submitted_review', 'module' => 'performance', 'description' => 'Can view finalized reviews'],
            ['name' => 'Share Review', 'slug' => 'share_review', 'module' => 'performance', 'description' => 'Can share and release performance reviews to employees'],
            ['name' => 'Reopen Review', 'slug' => 'reopen_review', 'module' => 'performance', 'description' => 'Can reopen completed reviews for modification'],
            ['name' => 'View All Reviews', 'slug' => 'view_all_reviews', 'module' => 'performance', 'description' => 'Can view performance reviews across the company'],

            // Module: reports
            ['name' => 'View Employee Reports', 'slug' => 'view_employee_reports', 'module' => 'reports', 'description' => 'Can view detailed reports on employees'],

            // Module: recruiting
            ['name' => 'Create Jobs', 'slug' => 'create_jobs', 'module' => 'recruiting', 'description' => 'Create and publish job postings'],
            ['name' => 'View Candidates', 'slug' => 'view_candidates', 'module' => 'recruiting', 'description' => 'View applicant profiles and details'],
            ['name' => 'Move Stages', 'slug' => 'move_stages', 'module' => 'recruiting', 'description' => 'Advance or reject candidates in the pipeline'],
            ['name' => 'Send Offers', 'slug' => 'send_offers', 'module' => 'recruiting', 'description' => 'Generate and send official job offers'],
            ['name' => 'Buy Job Ads', 'slug' => 'buy_job_ads', 'module' => 'recruiting', 'description' => 'Purchase premium advertising for job boards'],
            ['name' => 'Email Candidates', 'slug' => 'email_candidates', 'module' => 'recruiting', 'description' => 'Email applicants through the platform'],

            // Module: employees/users/settings/billing/finance/reports
            ['name' => 'View Salary', 'slug' => 'view_salary', 'module' => 'employees', 'description' => 'View confidential employee salary details'],
            ['name' => 'Background Checks', 'slug' => 'background_checks', 'module' => 'employees', 'description' => 'Order and view candidate background checks'],
            ['name' => 'Manage Users', 'slug' => 'manage_users', 'module' => 'users', 'description' => 'Add, edit, or deactivate user accounts'],
            ['name' => 'Account Settings', 'slug' => 'account_settings', 'module' => 'settings', 'description' => 'Modify main company and portal settings'],
            ['name' => 'HR Records', 'slug' => 'hr_records', 'module' => 'employees', 'description' => 'Access and edit standard employee profiles and files'],
            ['name' => 'Approve Time Off (API)', 'slug' => 'approve_timeoff', 'module' => 'time_off', 'description' => 'Approve or reject time-off requests via API'],
            ['name' => 'Payroll', 'slug' => 'payroll', 'module' => 'finance', 'description' => 'Access and run payroll reports'],
            ['name' => 'Reports (API)', 'slug' => 'reports', 'module' => 'reports', 'description' => 'Access dashboard analytics and HR exports via API'],
            ['name' => 'Billing', 'slug' => 'billing', 'module' => 'billing', 'description' => 'Update subscription and invoice details'],
            ['name' => 'Export Data', 'slug' => 'export_data', 'module' => 'settings', 'description' => 'Export bulk system data and audit logs'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                array_merge($permission, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        echo "Permissions seeded successfully!\n";
    }
}
