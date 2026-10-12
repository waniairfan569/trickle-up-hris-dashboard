<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a template drafts one document per event, or one a month listing
 * every event with its reason.
 *
 * Lateness is already monthly by nature, so any template assigned to late days
 * is set that way; everything else keeps drafting per event until an admin
 * changes it on the Documents page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_document_templates') || Schema::hasColumn('hr_document_templates', 'rollup')) {
            return;
        }

        Schema::table('hr_document_templates', function (Blueprint $table) {
            $table->string('rollup', 16)->default('event')->after('prefill');
        });

        DB::table('hr_document_templates')->where('prefill', 'lateness')->update(['rollup' => 'monthly']);
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_document_templates') && Schema::hasColumn('hr_document_templates', 'rollup')) {
            Schema::table('hr_document_templates', fn (Blueprint $t) => $t->dropColumn('rollup'));
        }
    }
};
