<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid attendance: the weekdays on which an employee works remotely (dashboard
 * clock-in) rather than on-site (biometric). e.g. ["Thu","Fri"]. Empty = the
 * employee always uses their base attendance_mode. Combined at run-time with
 * approved Work-From-Home requests to decide the effective mode for a given day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('remote_days')->nullable()->after('attendance_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('remote_days');
        });
    }
};
