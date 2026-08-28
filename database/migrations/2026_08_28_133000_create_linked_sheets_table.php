<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('url');
            $table->text('description')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('provider')->nullable();          // google | excel | airtable | link
            $table->string('visibility')->default('everyone'); // everyone | admins | departments
            $table->unsignedInteger('opens_count')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('linked_sheet_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linked_sheet_id')->constrained('linked_sheets')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->unique(['linked_sheet_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linked_sheet_department');
        Schema::dropIfExists('linked_sheets');
    }
};
