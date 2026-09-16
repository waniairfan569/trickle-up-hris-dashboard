<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an attendance day as worked remotely (Work From Home) so an approved
 * WFH day the employee actually clocked in on is distinguishable from a normal
 * on-site day — on the Live Board, My Attendance and in exports. Null means a
 * regular office day; 'remote' is stamped at clock-in when the date is covered
 * by an approved Work-From-Home request. WFH stays approval-based and never
 * consumes a leave balance; a WFH day with no clock-in is still Absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_records', 'work_location')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->string('work_location', 20)->nullable()->after('source');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_records', 'work_location')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumn('work_location');
            });
        }
    }
};
