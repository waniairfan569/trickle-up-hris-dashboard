<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A place with a QR code on a screen that people clock in by scanning.
 *
 * The code is not fixed. A fixed one can be photographed once and used from
 * home for ever, which makes it worse than nothing — it looks like proof of
 * attendance and is not. Each point holds a secret, and the code it displays
 * is derived from that secret plus the current few seconds, so a photograph
 * stops working almost immediately.
 *
 * Tied to an office where there is one, so a scan can be checked against the
 * same geofence a manual clock-in uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_clock_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->foreignId('office_location_id')->nullable()
                ->constrained('office_locations')->nullOnDelete();

            // Never shown to anybody. The displayed code is derived from it.
            $table->string('secret', 64);

            // How long a displayed code stays good for. Short enough that a
            // photograph is useless, long enough that a slow phone still works.
            $table->unsignedSmallInteger('rotate_seconds')->default(30);

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_clock_points');
    }
};
