<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unsigned HR documents were never chased. The hourly reminder job only ever
 * looked at e-signature requests, because the chasing was written on that side
 * and the two systems share no code — so a lateness review could sit unsigned
 * indefinitely with nobody nudged.
 *
 * Signers need somewhere to record that they have been reminded, the way the
 * e-signature side already does, so one reminder does not become a daily one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_document_signers', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('signed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('hr_document_signers', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
