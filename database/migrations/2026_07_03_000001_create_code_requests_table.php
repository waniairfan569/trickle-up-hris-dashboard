<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('tool_name');
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'code_sent'])->default('pending');
            $table->string('code_provided')->nullable();
            $table->timestamp('code_sent_at')->nullable();
            $table->string('code_expires_note')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_requests');
    }
};
