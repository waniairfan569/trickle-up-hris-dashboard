<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 'half_day' status: an employee on an approved half-day leave who clocks
     * in shows as Half Day, not Late. Without this enum value strict MySQL
     * rejects the save ("Data truncated for column 'status'") → 500 on every
     * attendance edit for such days.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN status ENUM(
            'present','late','absent','early_departure','overtime','on_leave',
            'public_holiday','weekend','missing_clock_out','correction_pending','half_day'
        ) NOT NULL DEFAULT 'absent'");
    }

    public function down(): void
    {
        DB::table('attendance_records')->where('status', 'half_day')->update(['status' => 'on_leave']);
        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN status ENUM(
            'present','late','absent','early_departure','overtime','on_leave',
            'public_holiday','weekend','missing_clock_out','correction_pending'
        ) NOT NULL DEFAULT 'absent'");
    }
};
