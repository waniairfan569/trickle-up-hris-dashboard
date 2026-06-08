<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create User
        DB::table('users')->insert([
            'id' => 1,
            'company_id' => 1,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@acme.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create corresponding Employee record (Rule 1)
        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'user_id' => 1,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@acme.com',
            'job_title' => 'Chief Administrator',
            'department_id' => 1,
            'location_id' => 1,
            'employee_id' => 'ADMIN-001',
            'employment_type' => 'full_time',
            'hire_date' => now()->subYear(),
            'status' => 'active',
            'salary' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // 3. Department Assignment
        DB::table('user_department_assignments')->insert([
            'user_id' => 1,
            'department_id' => 1, 
            'assigned_role' => 'Manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
