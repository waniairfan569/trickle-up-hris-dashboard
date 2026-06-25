<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADMS / push mode identifies a device by its serial number (SN), not its
     * IP. Add a nullable serial_number so push-registered devices can be matched
     * and auto-registered. Pull devices (matched by IP) are unaffected.
     */
    public function up(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->unique()->after('name');
            $table->string('connection_mode')->default('pull')->after('serial_number'); // pull | push
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->dropColumn(['serial_number', 'connection_mode']);
        });
    }
};
