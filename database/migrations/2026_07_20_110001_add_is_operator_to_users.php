<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_operator')->default(false);
        });

        // Mark the platform owner(s) — the default tenant's super admins — as
        // SaaS operators so they can manage all agencies.
        $defaultTenantId = DB::table('tenants')->where('slug', 'trickle-up')->value('id');
        if ($defaultTenantId) {
            $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');
            $userIds = DB::table('role_user')->where('role_id', $superAdminId)->pluck('user_id');
            DB::table('users')
                ->where('tenant_id', $defaultTenantId)
                ->whereIn('id', $userIds)
                ->update(['is_operator' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_operator');
        });
    }
};
