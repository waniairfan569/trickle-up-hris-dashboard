<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time correction: attendance rows stored as "absent" (no clock-in) that
     * fall on a company non-working day — per the Attendance Report Settings
     * working-days list (e.g. Sat/Sun unchecked) — are re-marked "weekend" so
     * they read as an off day, not an absence. Only applied to employees on the
     * company default (no personal work schedule); people with their own
     * schedule are left untouched. MySQL; safe to skip elsewhere.
     */
    public function up(): void
    {
        if (!Schema::hasTable('attendance_records') || !Schema::hasTable('attendance_report_settings')) {
            return;
        }

        try {
            $settings = DB::table('attendance_report_settings')->first();
            $working = $settings && $settings->working_days
                ? (json_decode($settings->working_days, true) ?: [])
                : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

            $map = ['Sun' => 1, 'Mon' => 2, 'Tue' => 3, 'Wed' => 4, 'Thu' => 5, 'Fri' => 6, 'Sat' => 7];
            $nonWorking = [];
            foreach ($map as $short => $num) {
                if (!in_array($short, $working, true)) {
                    $nonWorking[] = $num;
                }
            }
            if (empty($nonWorking)) {
                return; // every day is a working day — nothing to fix
            }

            $nullSchedUserIds = DB::table('users')->whereNull('work_schedule_id')->pluck('id')->all();
            if (empty($nullSchedUserIds)) {
                return;
            }

            DB::table('attendance_records')
                ->where('status', 'absent')
                ->whereNull('clock_in')
                ->whereNull('deleted_at')
                ->whereIn('user_id', $nullSchedUserIds)
                ->whereRaw('DAYOFWEEK(`date`) IN (' . implode(',', $nonWorking) . ')')
                ->update(['status' => 'weekend']);
        } catch (\Throwable $e) {
            // Never fail the migration over a data cleanup.
        }
    }

    public function down(): void
    {
        // Not reversible — can't tell which 'weekend' rows were previously 'absent'.
    }
};
