<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignSuperAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Get the Super Admin role ID
        $superAdminRole = DB::table('roles')->where('slug', 'super_admin')->first();

        if (!$superAdminRole) {
            echo "Super Admin role not found. Run RolesPermissionsSeeder first.\n";
            return;
        }

        // Assign Super Admin role to user ID 1 in pivot table
        DB::table('role_user')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $superAdminRole->id],
            [
                'assigned_by' => 1,
                'assigned_at' => now(),
            ]
        );

        echo "User [1] assigned to role: Super Admin\n";
    }
}
