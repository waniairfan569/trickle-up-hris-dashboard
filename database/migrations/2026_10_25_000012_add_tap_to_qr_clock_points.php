<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a clock-in point also be a sticker somebody taps a phone on.
 *
 * A point is a place people clock in at. Until now that place had to have a
 * screen, because the proof was a code that changes every few seconds. A tag
 * cannot change what it holds, so a sticker proves nothing on its own — and
 * the proof becomes standing inside the geofence instead.
 *
 * That swap is what makes this worth having. A site with a sticker needs no
 * screen, no power, no network and no device, which is the difference between
 * an agency putting a clock-in point at one office and at thirty client sites.
 *
 * Off by default, so no point that exists starts accepting anything new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_clock_points', function (Blueprint $table) {
            $table->boolean('accepts_tap')->default(false)->after('rotate_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('qr_clock_points', function (Blueprint $table) {
            $table->dropColumn('accepts_tap');
        });
    }
};
