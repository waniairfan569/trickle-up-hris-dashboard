<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ZKTeco devices report punches in their own configured local time.
     * This column records that device timezone so the sync can convert punches
     * into the application's canonical timezone before storing. When null, the
     * sync falls back to the primary company entity's timezone.
     */
    public function up(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->string('timezone')->nullable()->after('port');
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
