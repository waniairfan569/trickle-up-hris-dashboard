<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When true, this policy is automatically assigned to every new employee
     * (with a leave balance) the moment they're created. Defaults to true so
     * the standard leave policies flow to new hires without manual assignment.
     */
    public function up(): void
    {
        Schema::table('time_off_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('time_off_policies', 'auto_assign_to_new_employees')) {
                $table->boolean('auto_assign_to_new_employees')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_off_policies', function (Blueprint $table) {
            if (Schema::hasColumn('time_off_policies', 'auto_assign_to_new_employees')) {
                $table->dropColumn('auto_assign_to_new_employees');
            }
        });
    }
};
