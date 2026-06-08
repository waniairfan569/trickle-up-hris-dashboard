<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Roles
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Super Administrator with full access to all modules and configurations.',
                'is_system' => true,
            ],
            [
                'name' => 'HR Admin',
                'slug' => 'hr_admin',
                'description' => 'HR Administrator who manages employee profiles, records, and time off.',
                'is_system' => true,
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Manager with access to view candidates, run interviews, and approve team time off requests.',
                'is_system' => true,
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Regular employee with self-service portal access.',
                'is_system' => true,
            ],
            [
                'name' => 'Restricted',
                'slug' => 'restricted',
                'description' => 'Restricted user with minimal read-only access to selected areas.',
                'is_system' => true,
            ],
        ];

        $roleIds = [];
        foreach ($roles as $role) {
            $roleIds[$role['slug']] = DB::table('roles')->insertGetId(array_merge($role, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 2. Seed Permissions
        $permissions = [
            // Jobs / Recruiting
            ['name' => 'Create Jobs', 'slug' => 'create_jobs', 'module' => 'recruiting', 'description' => 'Create and publish job postings'],
            ['name' => 'View Candidates', 'slug' => 'view_candidates', 'module' => 'recruiting', 'description' => 'View applicant profiles and details'],
            ['name' => 'Move Stages', 'slug' => 'move_stages', 'module' => 'recruiting', 'description' => 'Advance or reject candidates in the pipeline'],
            ['name' => 'Send Offers', 'slug' => 'send_offers', 'module' => 'recruiting', 'description' => 'Generate and send official job offers'],
            ['name' => 'Buy Job Ads', 'slug' => 'buy_job_ads', 'module' => 'recruiting', 'description' => 'Purchase premium advertising for job boards'],
            ['name' => 'Email Candidates', 'slug' => 'email_candidates', 'module' => 'recruiting', 'description' => 'Email applicants through the platform'],

            // HR / Employees
            ['name' => 'View Salary', 'slug' => 'view_salary', 'module' => 'employees', 'description' => 'View confidential employee salary details'],
            ['name' => 'Background Checks', 'slug' => 'background_checks', 'module' => 'employees', 'description' => 'Order and view candidate background checks'],
            ['name' => 'Manage Users', 'slug' => 'manage_users', 'module' => 'users', 'description' => 'Add, edit, or deactivate user accounts'],
            ['name' => 'Account Settings', 'slug' => 'account_settings', 'module' => 'settings', 'description' => 'Modify main company and portal settings'],
            ['name' => 'HR Records', 'slug' => 'hr_records', 'module' => 'employees', 'description' => 'Access and edit standard employee profiles and files'],
            ['name' => 'Approve Time Off', 'slug' => 'approve_timeoff', 'module' => 'time_off', 'description' => 'Approve or reject time-off requests'],
            ['name' => 'Payroll', 'slug' => 'payroll', 'module' => 'finance', 'description' => 'Access and run payroll reports'],
            ['name' => 'Reports', 'slug' => 'reports', 'module' => 'reports', 'description' => 'Access dashboard analytics and HR exports'],
            ['name' => 'Billing', 'slug' => 'billing', 'module' => 'billing', 'description' => 'Update subscription and invoice details'],
            ['name' => 'Export Data', 'slug' => 'export_data', 'module' => 'settings', 'description' => 'Export bulk system data and audit logs'],
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $permissionIds[$perm['slug']] = DB::table('permissions')->insertGetId(array_merge($perm, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 3. Associate Permissions to Roles (Pivot Table)
        $rolePermissions = [
            'super_admin' => array_keys($permissionIds), // All permissions
            'hr_admin' => [
                'create_jobs', 'view_candidates', 'move_stages', 'send_offers', 'email_candidates',
                'view_salary', 'background_checks', 'hr_records', 'approve_timeoff', 'reports'
            ],
            'manager' => [
                'view_candidates', 'move_stages', 'email_candidates', 'hr_records', 'approve_timeoff'
            ],
            'employee' => [],
            'restricted' => [],
        ];

        foreach ($rolePermissions as $roleSlug => $perms) {
            $roleId = $roleIds[$roleSlug];
            foreach ($perms as $permSlug) {
                $permId = $permissionIds[$permSlug];
                DB::table('permission_role')->insert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }

        echo "Roles and Permissions seeded successfully!\n";
    }
}
