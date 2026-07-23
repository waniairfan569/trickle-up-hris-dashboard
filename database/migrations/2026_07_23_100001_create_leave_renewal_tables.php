<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave year renewal + encashment + pro-rata system.
     * Each company/policy can have its own leave-year window and encashment
     * rule; renewals snapshot everything into encashment records + run logs.
     */
    public function up(): void
    {
        Schema::create('leave_year_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->foreignId('policy_id')->constrained('time_off_policies')->cascadeOnDelete();
            $table->string('name');                                        // e.g. "Annual Leave 2025-26"
            $table->unsignedTinyInteger('year_start_month')->default(7);   // 1-12
            $table->unsignedTinyInteger('year_start_day')->default(1);     // 1-31
            $table->boolean('encashment_enabled')->default(false);
            $table->enum('encashment_type', ['percent_of_annual', 'full_remaining', 'fixed_days', 'none'])
                ->default('percent_of_annual');
            $table->decimal('encashment_value', 8, 2)->default(10.00);
            $table->unsignedInteger('working_days_per_month')->default(26);
            $table->boolean('carry_forward_enabled')->default(false);
            $table->decimal('carry_forward_max_days', 5, 1)->nullable();
            $table->boolean('pro_rata_enabled')->default(true);
            $table->unsignedTinyInteger('pro_rata_cutoff_day')->default(15);
            $table->enum('pro_rata_round_to', ['none', 'half', 'full'])->default('half');
            $table->boolean('auto_renewal_enabled')->default(true);
            $table->date('last_renewal_date')->nullable();
            $table->date('next_renewal_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['policy_id', 'is_active']);
            $table->index('next_renewal_date');
        });

        Schema::create('leave_encashment_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('time_off_policies')->cascadeOnDelete();
            $table->foreignId('leave_year_setting_id')->constrained('leave_year_settings')->cascadeOnDelete();
            $table->string('leave_year_label');                            // "July 2025 – June 2026"
            $table->unsignedInteger('renewal_year');
            $table->decimal('annual_allocation', 5, 1);                    // full OR pro-rata base
            $table->boolean('is_pro_rata')->default(false);
            $table->unsignedTinyInteger('pro_rata_months')->nullable();
            $table->decimal('days_remaining_before_renewal', 5, 1);
            $table->string('encashment_type');                             // rule snapshot
            $table->decimal('encashment_value', 8, 2);                     // rule snapshot
            $table->decimal('encashment_cap_days', 5, 1);
            $table->decimal('days_to_encash', 5, 1);
            $table->decimal('daily_rate', 10, 2);
            $table->decimal('monthly_salary_snapshot', 12, 2);
            $table->decimal('encashment_amount', 12, 2);
            $table->decimal('days_lapsed', 5, 1)->default(0);
            $table->string('currency', 10)->default('PKR');
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'renewal_year']);
            $table->index('status');
        });

        Schema::create('leave_renewal_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->foreignId('policy_id')->constrained('time_off_policies')->cascadeOnDelete();
            $table->foreignId('leave_year_setting_id')->constrained('leave_year_settings')->cascadeOnDelete();
            $table->date('renewal_date');
            $table->string('leave_year_label');
            $table->enum('triggered_by', ['automatic', 'manual'])->default('automatic');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_employees')->default(0);
            $table->unsignedInteger('employees_with_encashment')->default(0);
            $table->unsignedInteger('employees_no_encashment')->default(0);
            $table->decimal('total_encashment_amount', 14, 2)->default(0);
            $table->decimal('total_days_lapsed', 8, 1)->default(0);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_renewal_logs');
        Schema::dropIfExists('leave_encashment_records');
        Schema::dropIfExists('leave_year_settings');
    }
};
