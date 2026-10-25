<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a device says about itself.
 *
 * It has been telling us all along. The handshake and the uploads that are not
 * attendance carry the model, the firmware and a list of what the hardware can
 * do — and the push endpoint answered all of them with OK and kept nothing. So
 * "does this device read cards" had no answer anywhere in the system, and the
 * only way to find out was to walk up to it.
 *
 * Same lesson as the October outage: the facts were arriving, nobody wrote
 * them down. All nullable, filled in as devices speak up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->string('model')->nullable()->after('serial_number');
            $table->string('firmware')->nullable()->after('model');
            $table->string('platform')->nullable()->after('firmware');
            $table->json('capabilities')->nullable()->after('platform');
            $table->timestamp('described_at')->nullable()->after('capabilities');
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->dropColumn(['model', 'firmware', 'platform', 'capabilities', 'described_at']);
        });
    }
};
