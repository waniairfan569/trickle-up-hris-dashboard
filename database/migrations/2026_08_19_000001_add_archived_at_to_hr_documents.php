<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive support for filed HR documents: archived rows are hidden from the
 * main list but kept on file (distinct from a soft delete, which removes them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_documents', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('hr_documents', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
