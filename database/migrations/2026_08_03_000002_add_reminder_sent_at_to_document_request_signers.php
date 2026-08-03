<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track when we last emailed a signer a "please sign" reminder, so the
     * 24-hour reminder job doesn't nag on every run.
     */
    public function up(): void
    {
        Schema::table('document_request_signers', function (Blueprint $table) {
            if (!Schema::hasColumn('document_request_signers', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('signed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_request_signers', function (Blueprint $table) {
            if (Schema::hasColumn('document_request_signers', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
        });
    }
};
