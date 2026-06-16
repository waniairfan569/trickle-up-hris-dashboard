<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->json('tags')->nullable();
            // Uploaded file (stored on the private 'local' disk)
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
        });

        // Scoping: entities the template applies to (empty = all entities)
        Schema::create('document_template_entity', function (Blueprint $table) {
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_entity_id')->constrained()->cascadeOnDelete();
            $table->primary(['document_template_id', 'company_entity_id'], 'doc_tpl_entity_pk');
        });

        // Scoping: departments the template applies to (empty = all departments)
        Schema::create('document_template_department', function (Blueprint $table) {
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['document_template_id', 'department_id'], 'doc_tpl_dept_pk');
        });

        // Ordered signers
        Schema::create('document_template_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->enum('signer_type', ['role', 'employee'])->default('role');
            // role-based: employee | line_manager | hr_admin
            $table->string('role')->nullable();
            // specific employee
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Selected profile fields + signature blocks (with optional Phase-2 placement)
        Schema::create('document_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_field_id')->nullable()->constrained('profile_fields')->nullOnDelete();
            $table->string('field_key')->nullable();
            $table->string('label')->nullable();
            $table->string('section')->nullable();
            // text | signature | initials
            $table->string('field_type')->default('text');
            // who fills/signs: employee | sender | hr_admin | me_now
            $table->string('assignee')->default('employee');
            // Phase 2 placement (nullable until placed)
            $table->unsignedInteger('page')->nullable();
            $table->decimal('pos_x', 8, 3)->nullable();
            $table->decimal('pos_y', 8, 3)->nullable();
            $table->decimal('width', 8, 3)->nullable();
            $table->decimal('height', 8, 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_fields');
        Schema::dropIfExists('document_template_signers');
        Schema::dropIfExists('document_template_department');
        Schema::dropIfExists('document_template_entity');
        Schema::dropIfExists('document_templates');
    }
};
