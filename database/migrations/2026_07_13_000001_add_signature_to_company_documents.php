<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_documents', function (Blueprint $table) {
            $table->boolean('requires_signature')->default(false)->after('requires_acknowledgment');
            $table->foreignId('template_id')->nullable()->after('requires_signature')
                ->constrained('document_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('requires_signature');
        });
    }
};
