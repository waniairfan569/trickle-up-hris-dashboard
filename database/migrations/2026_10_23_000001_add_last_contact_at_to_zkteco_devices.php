<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the device last spoke to us at all — not when it last sent punches.
 *
 * "Last sync" only moves when a device POSTs attendance data, so a device that
 * is plugged in, online and polling every few seconds looks identical to one
 * that has been unplugged for a day. The two need completely different fixes,
 * and until now there was no way to tell them apart from the dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->timestamp('last_contact_at')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->dropColumn('last_contact_at');
        });
    }
};
