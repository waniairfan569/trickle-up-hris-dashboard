<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['in_progress', 'completed', 'declined', 'cancelled'])->default('in_progress');
            $table->unsignedInteger('current_position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_request_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // employee | line_manager | hr_admin | specific
            $table->string('source_role')->nullable();
            $table->enum('status', ['pending', 'signed', 'declined'])->default('pending');
            $table->longText('signature_image')->nullable(); // PNG data URL
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('document_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // created | viewed | signed | completed | declined | cancelled
            $table->string('detail')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_events');
        Schema::dropIfExists('document_request_signers');
        Schema::dropIfExists('document_requests');
    }
};
