<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a conduct note to an HR document — used by the auto "document not signed
 * after N days" conduct note, both to reference the document and to avoid logging
 * the same one twice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('conduct_notes', function (Blueprint $table) {
            $table->foreignId('hr_document_id')->nullable()->after('user_id')
                ->constrained('hr_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conduct_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hr_document_id');
        });
    }
};
