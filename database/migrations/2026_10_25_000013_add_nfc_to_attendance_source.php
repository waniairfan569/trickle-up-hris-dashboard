<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let an attendance row say it came from a tapped tag.
 *
 * Kept apart from 'qr' deliberately. The two prove presence by different
 * means — a scan proves standing at a screen, a tap proves standing inside a
 * geofence — and when somebody asks later how a punch was trusted, the row
 * should be able to say which of the two it was.
 *
 * Adding a value to an enum is additive: every existing row keeps what it has.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN source
            ENUM('web','zkteco','excel_import','manual','qr','nfc') NOT NULL DEFAULT 'web'");
    }

    public function down(): void
    {
        DB::table('attendance_records')->where('source', 'nfc')->update(['source' => 'web']);

        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN source
            ENUM('web','zkteco','excel_import','manual','qr') NOT NULL DEFAULT 'web'");
    }
};
