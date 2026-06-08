<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Super Administrator with full system access.',
                'is_system' => true,
            ],
            [
                'name' => 'HR Admin',
                'slug' => 'hr_admin',
                'description' => 'HR Administrator who manages employee profiles and HR records.',
                'is_system' => false,
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Manager with access to direct reports and team settings.',
                'is_system' => false,
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Regular employee with self-service features.',
                'is_system' => true,
            ],
            [
                'name' => 'Restricted',
                'slug' => 'restricted',
                'description' => 'Restricted employee with limited view-only access.',
                'is_system' => false,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                array_merge($role, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        echo "Roles seeded successfully!\n";
    }
}
