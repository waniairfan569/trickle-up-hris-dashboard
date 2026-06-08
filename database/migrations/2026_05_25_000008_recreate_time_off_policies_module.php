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
        // Drop existing tables to rebuild cleanly
        Schema::dropIfExists('time_off_balances');
        Schema::dropIfExists('policy_user');
        Schema::dropIfExists('time_off_policies');

        // 1. Create time_off_policies table
        Schema::create('time_off_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            
            // Core Rules
            $table->enum('type', ['annual', 'sick', 'unpaid', 'maternity', 'paternity', 'bereavement', 'custom']);
            $table->enum('accrual_type', ['none', 'monthly', 'annually'])->default('none');
            $table->decimal('days_per_year', 5, 1);
            $table->decimal('max_balance', 5, 1)->nullable(); // null means unlimited
            
            // Carry-over Rules
            $table->boolean('carry_over')->default(false);
            $table->decimal('carry_over_max', 5, 1)->nullable();
            
            // Approval Rules
            $table->boolean('requires_approval')->default(true);
            $table->enum('approval_type', ['manager', 'hr_admin', 'both'])->default('manager');
            $table->integer('min_notice_days')->default(0);
            
            // Additional Flags
            $table->boolean('allow_half_days')->default(true);
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Create policy_user table (Pivot for assigning policies)
        Schema::create('policy_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('time_off_policies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->decimal('custom_days_per_year', 5, 1)->nullable(); // Overrides policy default
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });

        // 3. Create time_off_balances table
        Schema::create('time_off_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('time_off_policies')->cascadeOnDelete();
            $table->integer('year');
            $table->decimal('opening_balance', 5, 1)->default(0);
            $table->decimal('accrued', 5, 1)->default(0);
            $table->decimal('used', 5, 1)->default(0);
            $table->decimal('pending', 5, 1)->default(0);
            $table->decimal('carried_over', 5, 1)->default(0);
            $table->decimal('adjusted', 5, 1)->default(0); // Manual HR adjustments
            $table->timestamps();

            // Unique constraint
            $table->unique(['user_id', 'policy_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_off_balances');
        Schema::dropIfExists('policy_user');
        Schema::dropIfExists('time_off_policies');
    }
};
