<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let an attendance row say it came from a scanned code.
 *
 * The column exists to answer "how did this person clock in", and it already
 * distinguishes the dashboard from a device from a spreadsheet. A scan is
 * none of those, and without this the row has to claim to be something it is
 * not — which is the one thing an attendance record must never do.
 *
 * Adding a value to an enum is additive: every existing row keeps what it has.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN source
            ENUM('web','zkteco','excel_import','manual','qr') NOT NULL DEFAULT 'web'");
    }

    public function down(): void
    {
        // Anything recorded as a scan becomes a plain web clock-in, since the
        // column would otherwise refuse the value it already holds.
        DB::table('attendance_records')->where('source', 'qr')->update(['source' => 'web']);

        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN source
            ENUM('web','zkteco','excel_import','manual') NOT NULL DEFAULT 'web'");
    }
};
