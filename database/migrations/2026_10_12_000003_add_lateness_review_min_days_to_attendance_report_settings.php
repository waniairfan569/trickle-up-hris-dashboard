<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many late days in a month before the month-end Lateness Review is drafted.
 * Defaults to 3, matching the point LatenessDeductionService already issues a
 * warning — so the document arrives exactly where the policy bites, not on
 * every stray late morning. 0 means draft one for anyone who was ever late.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_report_settings')
            || Schema::hasColumn('attendance_report_settings', 'lateness_review_min_days')) {
            return;
        }

        Schema::table('attendance_report_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('lateness_review_min_days')->default(3)->after('late_threshold_minutes');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_report_settings')
            && Schema::hasColumn('attendance_report_settings', 'lateness_review_min_days')) {
            Schema::table('attendance_report_settings', fn (Blueprint $t) => $t->dropColumn('lateness_review_min_days'));
        }
    }
};
