<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Fetch or create the admin user
        $userEmail = 'admin@company.com';
        
        // Delete any existing user with ID 1 to avoid conflicts
        DB::table('users')->where('id', 1)->delete();
        DB::table('users')->where('email', $userEmail)->delete();
        
        DB::table('users')->insert([
            'id' => 1,
            'company_id' => 1,
            'first_name' => 'System',
            'last_name' => 'Admin',
            'email' => $userEmail,
            'password' => Hash::make('password'),
            'status' => 'active',
            'account_status' => 'active',
            'must_change_password' => false,
            'employee_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create corresponding Employee record
        DB::table('employees')->where('id', 1)->delete();
        DB::table('employees')->where('email', $userEmail)->delete();
        
        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'user_id' => 1,
            'first_name' => 'System',
            'last_name' => 'Admin',
            'email' => $userEmail,
            'job_title' => 'Chief Administrator',
            'department_id' => 1,
            'location_id' => 1,
            'employee_id' => 'ADMIN-SYS-001',
            'employment_type' => 'full_time',
            'hire_date' => now()->subYear(),
            'status' => 'active',
            'salary' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Assign Super Admin role to this user in the pivot table
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        if ($superAdminRole) {
            DB::table('role_user')->updateOrInsert(
                ['user_id' => 1, 'role_id' => $superAdminRole->id],
                [
                    'assigned_by' => 1,
                    'assigned_at' => now(),
                ]
            );
        }

        // Also assign Department assignment for consistency
        DB::table('user_department_assignments')->updateOrInsert(
            ['user_id' => 1, 'department_id' => 1],
            [
                'assigned_role' => 'Manager',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        echo "Super Admin [admin@company.com] created and assigned to super_admin role successfully!\n";
    }
}
