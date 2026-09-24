<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a filled HR document and a document template each carry a letterhead choice.
 * A document falls back to its template's letterhead, then the workspace default.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hr_documents', function (Blueprint $table) {
            $table->foreignId('letterhead_id')->nullable()->after('hr_document_template_id')
                ->constrained('letterhead_templates')->nullOnDelete();
        });

        Schema::table('hr_document_templates', function (Blueprint $table) {
            $table->foreignId('letterhead_id')->nullable()->after('id')
                ->constrained('letterhead_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('letterhead_id');
        });
        Schema::table('hr_document_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('letterhead_id');
        });
    }
};
