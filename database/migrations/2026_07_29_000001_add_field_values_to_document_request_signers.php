<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee-fillable fields: values a signer types into the document (e.g. a
 * loan amount) before signing. Stored per signer as a bracket-token => value
 * map, then stamped into the final PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_request_signers', function (Blueprint $table) {
            if (!Schema::hasColumn('document_request_signers', 'field_values')) {
                $table->json('field_values')->nullable()->after('signature_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_request_signers', function (Blueprint $table) {
            if (Schema::hasColumn('document_request_signers', 'field_values')) {
                $table->dropColumn('field_values');
            }
        });
    }
};
