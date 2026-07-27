<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'acknowledged' to document_views.action so acknowledgment events can be
 * logged. Strict MySQL rejects unknown enum values ("Data truncated"), so the
 * value must exist before logView('acknowledged') is called.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE document_views MODIFY COLUMN action
            ENUM('view','download','version_updated','acknowledged') NOT NULL DEFAULT 'view'");
    }

    public function down(): void
    {
        DB::table('document_views')->where('action', 'acknowledged')->delete();
        DB::statement("ALTER TABLE document_views MODIFY COLUMN action
            ENUM('view','download','version_updated') NOT NULL DEFAULT 'view'");
    }
};
