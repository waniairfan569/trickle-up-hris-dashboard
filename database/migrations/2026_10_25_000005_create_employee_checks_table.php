<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-employment and ongoing checks: right to work, DBS, a driving licence, a
 * professional registration.
 *
 * No provider is integrated here and none is pretended. Most of these are done
 * by a person looking at a document, and the obligation that actually bites is
 * not doing the check — it is proving later that it was done, by whom, and
 * noticing when it stops being valid. A visa expires and the right-to-work
 * check expires with it; a DBS gets old enough that a client asks for a new
 * one.
 *
 * So this records the check and its evidence, and gives it a date it stops
 * counting. Wiring a provider in later means filling these same fields from an
 * API instead of from a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 40);                       // right_to_work, dbs, …
            $table->string('status', 20)->default('pending'); // pending, passed, failed
            $table->string('reference', 120)->nullable();      // share code, certificate number
            $table->string('verified_by_name', 120)->nullable(); // provider, or the person who looked
            $table->date('checked_on')->nullable();
            $table->date('expires_on')->nullable();            // null means it does not expire
            $table->text('notes')->nullable();

            // The evidence, where there is any — a scan of the passport page,
            // the certificate. Kept as an employee document so it lives with
            // the rest of their file and inherits the same access rules.
            $table->foreignId('document_id')->nullable()
                ->constrained('employee_documents')->nullOnDelete();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index('expires_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_checks');
    }
};
