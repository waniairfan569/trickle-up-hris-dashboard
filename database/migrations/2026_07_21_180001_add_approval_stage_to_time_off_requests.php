<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks which approval level a pending request is waiting on, for the
     * "Manager then HR Admin" (both) two-stage flow: 'manager' → 'hr_admin' → done.
     * Null for single-stage policies.
     */
    public function up(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('time_off_requests', 'approval_stage')) {
                $table->string('approval_stage')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            if (Schema::hasColumn('time_off_requests', 'approval_stage')) {
                $table->dropColumn('approval_stage');
            }
        });
    }
};
