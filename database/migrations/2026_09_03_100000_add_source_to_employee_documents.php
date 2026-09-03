<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an employee_document be a *link* to a signed e-signature document (rather
 * than an uploaded file), so a completed signature request auto-appears in the
 * employee's profile → Uploads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_documents', 'source_type')) {
                $table->string('source_type')->nullable()->after('uploaded_by'); // e.g. 'signature'
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                $table->index(['source_type', 'source_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            if (Schema::hasColumn('employee_documents', 'source_type')) {
                $table->dropColumn(['source_type', 'source_id']);
            }
        });
    }
};
