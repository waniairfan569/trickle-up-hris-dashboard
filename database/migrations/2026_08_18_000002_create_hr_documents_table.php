<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A filled copy of an HR document template, tied to one employee and kept on
 * file as a historical record. We snapshot the template `schema` and store the
 * filled `data` (including signature PNG data-URLs) so the document always
 * renders exactly as it was completed, even if the template later changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('hr_document_template_id')->nullable();  // source template (may be deleted later)
            $table->foreignId('user_id')->index();                     // the employee the document is about
            $table->string('template_name');                           // denormalised snapshot
            $table->string('title')->nullable();                       // e.g. "Lateness Review — Aug 2026"
            $table->json('schema');                                    // snapshot of the template structure
            $table->longText('data')->nullable();                      // filled field values (JSON, incl. signatures)
            $table->date('period_start')->nullable();                  // prefill / reporting window
            $table->date('period_end')->nullable();
            $table->string('status')->default('draft');                // draft | completed
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_documents');
    }
};
