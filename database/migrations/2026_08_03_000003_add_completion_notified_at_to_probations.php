<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track when the "probation completed" notice was emailed, so the daily
     * job (and an admin confirmation) never notify the same probation twice.
     */
    public function up(): void
    {
        Schema::table('probations', function (Blueprint $table) {
            if (!Schema::hasColumn('probations', 'completion_notified_at')) {
                $table->timestamp('completion_notified_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('probations', function (Blueprint $table) {
            if (Schema::hasColumn('probations', 'completion_notified_at')) {
                $table->dropColumn('completion_notified_at');
            }
        });
    }
};
