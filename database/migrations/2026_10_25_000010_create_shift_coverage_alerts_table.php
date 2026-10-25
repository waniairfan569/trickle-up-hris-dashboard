<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which uncovered shifts have already been reported, so the daily watcher
 * mentions a hole once rather than every morning until somebody fills it.
 *
 * A row is deleted when the hole is filled, so if it reopens — cover cancels,
 * leave is extended — it is reported again. That is the behaviour wanted:
 * silence should mean "still the same hole", never "nobody is watching".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_coverage_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'shift_id', 'date'], 'coverage_alert_once');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_coverage_alerts');
    }
};
