<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The five built-in RBAC roles are referenced by code (Role::HR_ADMIN, …)
     * and must never be renamed or deleted. Some installs had them flagged
     * is_system = false, which would let the Roles manager delete/rename them.
     * Normalise them all to is_system = true.
     */
    public function up(): void
    {
        Role::withTrashed()->whereIn('slug', [
            Role::SUPER_ADMIN, Role::HR_ADMIN, Role::MANAGER, Role::EMPLOYEE, Role::RESTRICTED,
        ])->update(['is_system' => true]);
    }

    public function down(): void
    {
        // No-op: we don't want to un-protect the built-in roles.
    }
};
