<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_returns')) {
            return;
        }

        Schema::create('leave_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('time_off_request_id')->constrained('time_off_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // First working day the employee is back — everything from here to the
            // original end date is credited back to their balance.
            $table->date('return_date');
            $table->decimal('days_returned', 8, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable();               // employee note
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();           // HR note on decision
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['time_off_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_returns');
    }
};
