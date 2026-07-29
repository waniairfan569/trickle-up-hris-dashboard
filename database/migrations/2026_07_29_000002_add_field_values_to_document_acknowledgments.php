<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee-fillable fields on acknowledge-only documents: the values a person
 * types (e.g. a loan amount) when acknowledging, stored as a bracket-token =>
 * value map alongside their acknowledgment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_acknowledgments', function (Blueprint $table) {
            if (!Schema::hasColumn('document_acknowledgments', 'field_values')) {
                $table->json('field_values')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_acknowledgments', function (Blueprint $table) {
            if (Schema::hasColumn('document_acknowledgments', 'field_values')) {
                $table->dropColumn('field_values');
            }
        });
    }
};
