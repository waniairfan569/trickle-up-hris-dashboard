<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_report_settings', function (Blueprint $table) {
            // The zone the daily report's send_time is interpreted in. Defaults to
            // UK time so the report lands at the configured local hour regardless
            // of the server's timezone (UTC in production). DST is handled by the
            // named zone.
            $table->string('timezone', 64)->default('Europe/London')->after('send_time');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_report_settings', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
