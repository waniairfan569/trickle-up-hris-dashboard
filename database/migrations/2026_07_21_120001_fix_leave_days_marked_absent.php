<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time correction: approved leave days that were stored as "absent"
     * (notably half-day / hourly leave, which the old code skipped) are updated
     * to "on_leave" so they read as leave, not an absence, on every sheet.
     * Only untouched no-show days are corrected — days the employee actually
     * clocked in, or that are holidays/weekends, are left as-is. Tenant-agnostic.
     */
    public function up(): void
    {
        if (!Schema::hasTable('time_off_requests') || !Schema::hasTable('attendance_records')) {
            return;
        }

        DB::table('time_off_requests')
            ->where('status', 'approved')
            ->orderBy('id')
            ->chunk(200, function ($leaves) {
                foreach ($leaves as $lv) {
                    try {
                        $start = Carbon::parse($lv->start_date)->startOfDay();
                        $end = Carbon::parse($lv->end_date)->startOfDay();

                        // Guard against absurd ranges (bad data) — cap at ~1 year.
                        if ($start->diffInDays($end) > 366) {
                            continue;
                        }

                        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                            DB::table('attendance_records')
                                ->where('user_id', $lv->user_id)
                                ->whereDate('date', $d->toDateString())
                                ->whereNull('clock_in')
                                ->where('status', 'absent')
                                ->whereNull('deleted_at')
                                ->update(['status' => 'on_leave']);
                        }
                    } catch (\Throwable $e) {
                        // Skip any malformed row; never fail the migration.
                    }
                }
            });
    }

    public function down(): void
    {
        // Not reversible — we can't know which 'on_leave' rows were previously
        // 'absent'. No-op.
    }
};
