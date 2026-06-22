<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_report_logs', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->timestamp('sent_at')->nullable();
            $table->json('sent_to')->nullable();
            $table->integer('total_employees')->default(0);
            $table->integer('present_count')->default(0);
            $table->integer('late_count')->default(0);
            $table->integer('absent_count')->default(0);
            $table->integer('on_leave_count')->default(0);
            $table->enum('status', ['sent', 'failed', 'skipped'])->default('sent');
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['scheduled', 'manual'])->default('scheduled');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_report_logs');
    }
};
