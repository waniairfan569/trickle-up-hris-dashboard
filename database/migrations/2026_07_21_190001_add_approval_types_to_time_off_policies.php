<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widen the approval-type options: add "Super Admin Only" and the two-stage
     * "Manager then Super Admin". Employees / restricted roles can never approve.
     */
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE time_off_policies MODIFY approval_type ENUM('manager','hr_admin','both','super_admin','manager_super') NOT NULL DEFAULT 'manager'");
        } catch (\Throwable $e) {
            // Non-MySQL / already widened — safe to ignore.
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE time_off_policies MODIFY approval_type ENUM('manager','hr_admin','both') NOT NULL DEFAULT 'manager'");
        } catch (\Throwable $e) {
            //
        }
    }
};
