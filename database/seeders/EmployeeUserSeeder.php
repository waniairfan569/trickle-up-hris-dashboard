<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;

class EmployeeUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Fetch or create the test employee user
        $userEmail = 'employee@company.com';

        // Delete any existing user with ID 2 to avoid conflicts
        DB::table('users')->where('id', 2)->delete();
        DB::table('users')->where('email', $userEmail)->delete();

        DB::table('users')->insert([
            'id' => 2,
            'company_id' => 1,
            'first_name' => 'Test',
            'last_name' => 'Employee',
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
        DB::table('employees')->where('id', 2)->delete();
        DB::table('employees')->where('email', $userEmail)->delete();

        DB::table('employees')->insert([
            'id' => 2,
            'company_id' => 1,
            'user_id' => 2,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => $userEmail,
            'job_title' => 'Staff Member',
            'department_id' => 1,
            'location_id' => 1,
            'employee_id' => 'EMP-001',
            'employment_type' => 'full_time',
            'hire_date' => now()->subMonths(6),
            'status' => 'active',
            'salary' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Assign the Employee role to this user in the pivot table
        $employeeRole = Role::where('slug', 'employee')->first();
        if ($employeeRole) {
            DB::table('role_user')->updateOrInsert(
                ['user_id' => 2, 'role_id' => $employeeRole->id],
                [
                    'assigned_by' => 1,
                    'assigned_at' => now(),
                ]
            );
        }

        echo "Test Employee [employee@company.com] created and assigned to employee role successfully!\n";
    }
}
