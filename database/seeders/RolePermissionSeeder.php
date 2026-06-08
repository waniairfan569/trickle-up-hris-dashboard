<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch all roles & permissions
        $roles = Role::all()->keyBy('slug');
        $permissions = Permission::all()->keyBy('slug');

        if ($roles->isEmpty() || $permissions->isEmpty()) {
            echo "Warning: Roles or Permissions are empty. Run RoleSeeder and PermissionSeeder first.\n";
            return;
        }

        // Define permissions for each role
        $rolePermissions = [
            'super_admin' => $permissions->keys()->toArray(), // ALL permissions
            
            'hr_admin' => $permissions->reject(function ($perm) {
                return in_array($perm->slug, ['manage_time_off_policies', 'reopen_review']);
            })->keys()->toArray(),
            
            'manager' => [
                'view_team_profiles',
                'edit_direct_reports',
                'approve_time_off',
                'view_time_off_reports',
                'write_manager_review',
                'view_onboarding_dashboard',
                'upload_own_files',
                'view_candidates',
                'move_stages',
                'email_candidates',
                'hr_records',
                'approve_timeoff',
            ],
            
            'employee' => [
                'view_own_profile',
                'edit_own_profile',
                'request_time_off',
                'write_self_review',
                'upload_own_files',
            ],
            
            'restricted' => [
                'view_own_profile',
                'request_time_off',
                'write_self_review',
            ],
        ];

        // Clear existing associations to ensure it is clean
        DB::table('permission_role')->delete();

        // Populate associations
        foreach ($rolePermissions as $roleSlug => $permSlugs) {
            $role = $roles->get($roleSlug);
            if (!$role) {
                continue;
            }

            $attachIds = [];
            foreach ($permSlugs as $slug) {
                $perm = $permissions->get($slug);
                if ($perm) {
                    $attachIds[] = $perm->id;
                }
            }

            if (!empty($attachIds)) {
                $role->permissions()->sync($attachIds);
            }
        }

        echo "Role permissions populated successfully!\n";
    }
}
