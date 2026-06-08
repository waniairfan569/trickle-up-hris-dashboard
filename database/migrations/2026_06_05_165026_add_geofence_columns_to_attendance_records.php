<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->integer('distance_at_clock_in')->nullable();
            $table->integer('distance_at_clock_out')->nullable();
            $table->boolean('geofence_bypassed')->default(false);
            $table->text('bypass_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['office_location_id']);
            $table->dropColumn([
                'office_location_id',
                'distance_at_clock_in',
                'distance_at_clock_out',
                'geofence_bypassed',
                'bypass_reason'
            ]);
        });
    }
};
