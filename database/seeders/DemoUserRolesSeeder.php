<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;

class DemoUserRolesSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::where('slug', 'super_admin')->first();
        $hrAdmin = Role::where('slug', 'hr_admin')->first();
        $manager = Role::where('slug', 'manager')->first();
        $employee = Role::where('slug', 'employee')->first();

        if (!$superAdmin || !$hrAdmin || !$manager || !$employee) {
            echo "Roles not fully seeded. Please seed Roles first.\n";
            return;
        }

        // Map users to roles
        $mapping = [
            'admin@company.com' => $superAdmin,
            'demo0@company.com' => $hrAdmin,
            'demo1@company.com' => $manager,
            'demo2@company.com' => $manager,
            'demo3@company.com' => $employee,
            'demo4@company.com' => $employee,
            'demo5@company.com' => $employee,
            'demo6@company.com' => $employee,
            'demo7@company.com' => $employee,
            'demo8@company.com' => $employee,
            'demo9@company.com' => $employee,
        ];

        foreach ($mapping as $email => $role) {
            $user = User::where('email', $email)->first();
            if ($user) {
                // Attach role to user in role_user pivot table if not already attached
                DB::table('role_user')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $role->id],
                    [
                        'assigned_by' => 1,
                        'assigned_at' => now(),
                    ]
                );
                echo "Assigned {$email} to role: {$role->slug}\n";
            }
        }
    }
}
