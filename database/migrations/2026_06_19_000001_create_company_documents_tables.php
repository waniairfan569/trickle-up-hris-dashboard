<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_entity_id')->nullable();
            $table->foreignId('category_id')->constrained('document_categories')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_type')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->string('version')->default('1.0');
            $table->text('version_notes')->nullable();
            $table->enum('access_level', ['company_wide', 'department', 'specific_users'])->default('company_wide');
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_acknowledgment')->default(false);
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('company_documents')->cascadeOnDelete();
            $table->enum('access_type', ['department', 'user']);
            $table->unsignedBigInteger('access_id');
            $table->timestamps();
            $table->index(['document_id', 'access_type', 'access_id']);
        });

        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('company_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['view', 'download', 'version_updated'])->default('view');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_views');
        Schema::dropIfExists('document_access');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('document_categories');
    }
};
