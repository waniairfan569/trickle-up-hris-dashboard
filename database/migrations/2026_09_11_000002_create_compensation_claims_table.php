<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An employee's claim for compensation leave (time off in lieu): "I worked
 * overtime on <date>, please credit me <n> day(s)". HR / super admin approves
 * — which credits the compensatory policy balance — or declines it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compensation_claims')) {
            return;
        }

        Schema::create('compensation_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('worked_date');                              // the overtime day being claimed
            $table->decimal('hours_worked', 5, 2)->nullable();        // extra hours, as stated by the employee
            $table->decimal('days_claimed', 5, 2);                    // days asked for (0.5 steps)
            $table->decimal('days_credited', 5, 2)->nullable();       // what HR actually credited on approval
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_claims');
    }
};
