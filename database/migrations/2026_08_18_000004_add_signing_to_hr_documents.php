<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Send for signature" support: mark when a document was sent, and track each
 * assigned signer (the subject employee + their line manager) and which
 * signature fields they fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_documents', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
        });

        Schema::create('hr_document_signers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('hr_document_id')->index();
            $table->foreignId('user_id')->index();
            $table->string('role')->nullable();          // employee | manager
            $table->json('field_ids');                    // signature field ids this signer fills
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_signers');
        Schema::table('hr_documents', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};
