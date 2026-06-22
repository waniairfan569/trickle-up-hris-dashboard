<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('allow_multiple_submissions')->default(false);
            $table->boolean('show_progress_bar')->default(true);
            $table->boolean('requires_signature')->default(false);
            $table->dateTime('deadline')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('company_forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('field_key');
            $table->string('type'); // text, textarea, number, email, phone, date, dropdown, multi_select, checkbox, radio, file_upload, heading, paragraph, divider, signature
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->string('help_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('width', ['full', 'half'])->default('full');
            $table->timestamps();
        });

        Schema::create('form_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('company_forms')->cascadeOnDelete();
            $table->enum('assigned_to_type', ['user', 'department', 'all']);
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->dateTime('deadline_override')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamps();
            $table->unique(['form_id', 'assigned_to_type', 'assigned_to_id'], 'form_assignment_unique');
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('company_forms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('form_assignments')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'submitted'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->longText('signature_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained('form_fields')->nullOnDelete();
            $table->string('field_key');
            $table->string('field_label');
            $table->longText('value')->nullable();
            $table->timestamps();
            $table->unique(['submission_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_assignments');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('company_forms');
    }
};
