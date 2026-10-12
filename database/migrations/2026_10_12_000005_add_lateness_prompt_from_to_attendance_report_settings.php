<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The earliest late day the same-day prompt will ask about.
 *
 * Defaults to the day this runs, so switching the feature on asks about late
 * days from here onward instead of confronting everyone with weeks of history
 * the moment it is deployed. Clear it to ask about older days too.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_report_settings')
            || Schema::hasColumn('attendance_report_settings', 'lateness_prompt_from')) {
            return;
        }

        Schema::table('attendance_report_settings', function (Blueprint $table) {
            $table->date('lateness_prompt_from')->nullable()->after('lateness_review_min_days');
        });

        DB::table('attendance_report_settings')->update(['lateness_prompt_from' => now()->toDateString()]);
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_report_settings')
            && Schema::hasColumn('attendance_report_settings', 'lateness_prompt_from')) {
            Schema::table('attendance_report_settings', fn (Blueprint $t) => $t->dropColumn('lateness_prompt_from'));
        }
    }
};
