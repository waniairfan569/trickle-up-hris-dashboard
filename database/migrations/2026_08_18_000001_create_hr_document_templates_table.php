<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable HR document templates (Lateness Review, Return to Work, …).
 *
 * A template's structure lives in `schema` (JSON): an ordered list of sections,
 * each holding fields of a given type (text / textarea / date / checkbox /
 * radio / select / table / signature / note). Filled copies are stored in the
 * `hr_documents` table with a snapshot of the schema so historical records
 * always render exactly as they were completed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('subtitle')->nullable();          // small line under the title
            $table->text('description')->nullable();          // shown on the picker card
            $table->string('icon')->nullable();               // lucide icon name
            $table->string('prefill')->nullable();            // 'lateness' | 'absence' | null
            $table->json('schema');                           // sections + fields
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);     // seeded built-ins
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_templates');
    }
};
