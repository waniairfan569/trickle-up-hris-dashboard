<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An employee's own account of an attendance exception, captured on the day it
 * happened rather than reconstructed weeks later in a document.
 *
 * One row per attendance day. The month-end Lateness Review reads these to fill
 * its date/reason table, so the employee is never asked the same question twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_explanations')) {
            return;
        }

        Schema::create('attendance_explanations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 32)->default('late');     // late | absent | … room to grow
            $table->unsignedInteger('late_minutes')->default(0);   // as it stood when explained
            $table->text('reason');
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_ip', 64)->nullable();
            // Set once the day has been rolled into a month-end document.
            $table->foreignId('hr_document_id')->nullable()->constrained('hr_documents')->nullOnDelete();
            $table->timestamps();

            // One explanation per attendance day.
            $table->unique('attendance_record_id');
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_explanations');
    }
};
