<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('version')->default('1.0');
            $table->enum('category', ['hr', 'it', 'finance', 'legal', 'health_safety', 'general'])->default('general');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->boolean('requires_acknowledgment')->default(true);
            $table->boolean('requires_signature')->default(false);
            $table->string('document_file')->nullable();
            $table->string('document_filename')->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('review_date')->nullable();
            $table->integer('acknowledgment_deadline')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('policy_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('company_policies')->cascadeOnDelete();
            $table->enum('assigned_to_type', ['user', 'department', 'all']);
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->date('deadline')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_id', 'assigned_to_type', 'assigned_to_id'], 'policy_assignment_unique');
        });

        Schema::create('policy_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('company_policies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('policy_assignments')->nullOnDelete();
            $table->enum('status', ['pending', 'viewed', 'acknowledged'])->default('pending');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->enum('signature_type', ['typed', 'drawn'])->nullable();
            $table->string('signature_name')->nullable();
            $table->longText('signature_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->unique(['policy_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acknowledgments');
        Schema::dropIfExists('policy_assignments');
        Schema::dropIfExists('company_policies');
    }
};
