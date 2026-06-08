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
        // Drop existing tables if any
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('break_records');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_settings');

        // Migration 1: create_attendance_settings_table
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->integer('grace_period_minutes')->default(15);
            $table->integer('overtime_threshold_minutes')->default(30);
            $table->integer('early_departure_threshold_minutes')->default(15);
            $table->boolean('allow_break_tracking')->default(true);
            $table->boolean('allow_gps_capture')->default(false);
            $table->boolean('allow_manual_entry')->default(true);
            $table->integer('max_break_duration_minutes')->default(60);
            $table->timestamps();
        });

        // Migration 2: create_attendance_records_table
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            
            $table->enum('status', [
                'present', 'late', 'absent', 'early_departure', 
                'overtime', 'on_leave', 'public_holiday', 'weekend', 
                'missing_clock_out', 'correction_pending'
            ])->default('absent');
            
            $table->string('clock_in_ip')->nullable();
            $table->string('clock_out_ip')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            
            $table->integer('total_minutes_worked')->nullable();
            $table->integer('overtime_minutes')->nullable()->default(0);
            $table->integer('late_minutes')->nullable()->default(0);
            $table->integer('early_departure_minutes')->nullable()->default(0);
            
            $table->text('notes')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['user_id', 'date']);
        });

        // Migration 3: create_break_records_table
        Schema::create('break_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->timestamp('break_start');
            $table->timestamp('break_end')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->enum('break_type', ['lunch', 'short', 'other'])->default('short');
            $table->timestamps();
        });

        // Migration 4: create_attendance_corrections_table
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->date('correction_date');
            
            $table->timestamp('requested_clock_in')->nullable();
            $table->timestamp('requested_clock_out')->nullable();
            $table->text('reason');
            
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_note')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('break_records');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_settings');
    }
};
