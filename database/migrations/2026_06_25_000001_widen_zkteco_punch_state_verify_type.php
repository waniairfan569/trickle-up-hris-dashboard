<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tinyInteger (signed, max 127) overflowed for the SpeedFace-V5L, which
     * reports verify_type 255 (and higher punch_state codes). Widen both to
     * unsignedSmallInteger (0-65535) so any ZKTeco device's codes fit. The
     * K50's existing 0/1 values are unaffected.
     */
    public function up(): void
    {
        Schema::table('zkteco_raw_punches', function (Blueprint $table) {
            $table->unsignedSmallInteger('punch_state')->default(0)->change();
            $table->unsignedSmallInteger('verify_type')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_raw_punches', function (Blueprint $table) {
            $table->tinyInteger('punch_state')->default(0)->change();
            $table->tinyInteger('verify_type')->default(1)->change();
        });
    }
};
