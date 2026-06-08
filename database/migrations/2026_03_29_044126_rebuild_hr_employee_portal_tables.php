<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop existing time_off_requests related tables
        Schema::dropIfExists('time_off_audit_logs');
        Schema::dropIfExists('time_off_requests');

        // 2. Create leave_balances
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('company_id');
            $table->integer('leave_year');           

            // Planned leaves
            $table->decimal('annual_total', 5, 1)->default(20);
            $table->decimal('annual_used',  5, 1)->default(0);
            $table->decimal('annual_pending', 5, 1)->default(0);

            // Unplanned leaves
            $table->decimal('sick_total',   5, 1)->default(10);
            $table->decimal('sick_used',    5, 1)->default(0);

            // Unpaid
            $table->decimal('unpaid_used',  5, 1)->default(0);

            // Special
            $table->decimal('birthday_total',   5, 1)->default(1);
            $table->decimal('birthday_used',    5, 1)->default(0);

            // Parental
            $table->decimal('parental_total',   5, 1)->default(90);
            $table->decimal('parental_used',    5, 1)->default(0);

            // Comp off 
            $table->decimal('comp_off_earned',  5, 1)->default(0);
            $table->decimal('comp_off_used',    5, 1)->default(0);
            $table->date('comp_off_expires')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'leave_year']);
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // 3. Create time_off_requests (v2)
        Schema::create('time_off_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('approver_id')->nullable(); 

            $table->enum('type', ['annual','sick','unpaid','birthday','parental','comp_off','other']);
            $table->enum('category', ['planned','unplanned','special'])->default('planned');

            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_count', 5, 1); // Renamed from days_requested to match App logic
            $table->boolean('half_day')->default(false);
            $table->enum('half_day_period', ['morning','afternoon'])->nullable();

            $table->text('reason')->nullable();
            $table->enum('status', ['pending','approved','rejected','cancelled','revoked'])->default('pending');

            // Admin & Status actions
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();      // Added for Rule 13 consistency
            $table->unsignedBigInteger('revoked_by')->nullable(); // Added
            $table->text('revoke_reason')->nullable();         // Added
            
            $table->boolean('is_admin_created')->default(false); // Added for Rule 8 tracking
            $table->unsignedBigInteger('created_by_admin')->nullable(); // Added
            $table->text('admin_note')->nullable(); // Added
            
            $table->boolean('is_overridden')->default(false); // Added for manual corrections
            $table->text('override_reason')->nullable();     // Added
            $table->string('original_status', 50)->nullable(); // Added

            // Coverage
            $table->string('covering_employee', 255)->nullable();
            $table->boolean('handover_completed')->default(false);

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('approver_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'start_date', 'end_date']);
            $table->index(['employee_id', 'status']);
        });

        // 4. Create public_holidays
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 255);
            $table->date('date');
            $table->integer('year');
            $table->string('country_code', 10)->default('US');
            $table->enum('type', ['national','regional','company'])->default('national');
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'year', 'country_code']);
        });

        // 5. Create attendance_records
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('company_id');
            $table->date('work_date');
            
            $table->timestamp('clocked_in_at')->nullable();
            $table->timestamp('clocked_out_at')->nullable();
            
            $table->enum('status', ['working', 'on_break', 'clocked_out', 'completed'])->default('working');
            $table->enum('approval_status', ['pending', 'needs_review', 'approved'])->default('pending');
            $table->enum('work_type', ['office', 'remote', 'client_site'])->default('office');
            
            $table->integer('worked_minutes')->default(0);
            $table->integer('total_break_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            
            $table->string('location_name', 255)->nullable();
            $table->boolean('is_manual_entry')->default(false);
            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['employee_id', 'work_date']);
        });

        // 6. Create break_records
        Schema::create('break_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_record_id');
            
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->default(0);
            
            $table->timestamps();

            $table->foreign('attendance_record_id')->references('id')->on('attendance_records')->cascadeOnDelete();
        });

        // 7. Create time_off_audit_logs
        Schema::create('time_off_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('time_off_request_id');
            $table->unsignedBigInteger('performed_by');
            
            $table->string('action', 100);
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->json('previous_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('time_off_request_id')->references('id')->on('time_off_requests')->cascadeOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['company_id', 'time_off_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off_audit_logs');
        Schema::dropIfExists('break_records');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('time_off_requests');
        Schema::dropIfExists('leave_balances');
    }
};
