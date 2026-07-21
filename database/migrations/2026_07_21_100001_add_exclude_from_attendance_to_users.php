<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins can hide an individual employee from every attendance sheet and
     * report. When true: no daily absent/present record is generated for them,
     * and the live board / team history / reports / daily email all skip them —
     * they simply don't appear (not absent, not present, nothing).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'exclude_from_attendance')) {
                $table->boolean('exclude_from_attendance')->default(false)->after('attendance_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'exclude_from_attendance')) {
                $table->dropColumn('exclude_from_attendance');
            }
        });
    }
};
