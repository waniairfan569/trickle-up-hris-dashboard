<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a device went quiet, and whether anybody has been told.
 *
 * A device polls every few seconds. Silence for an hour is already abnormal,
 * and silence for a day means a day of attendance nobody recorded — which is
 * exactly what happened on 22 October: people clocked in, the device thanked
 * them, and the first anybody knew was somebody asking why the dashboard was
 * empty the next morning.
 *
 * Kept on the device rather than worked out each run, so the same outage is
 * reported once instead of every hour until it is fixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->timestamp('silent_since')->nullable()->after('last_contact_at');
            $table->timestamp('silence_notified_at')->nullable()->after('silent_since');
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->dropColumn(['silent_since', 'silence_notified_at']);
        });
    }
};
