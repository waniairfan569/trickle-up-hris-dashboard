<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A document had two signature slots and no order between them — whoever
 * opened it first could sign first. An agency that needs HR to approve before
 * the director, or the director before the employee, had no way to say so.
 *
 * Signers now carry the position they sign in. Zero means "no position", which
 * is how every existing signer starts and what every template means until
 * somebody sets an order — so documents already out for signature keep behaving
 * exactly as they do today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_document_signers', function (Blueprint $table) {
            $table->unsignedTinyInteger('sign_order')->default(0)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('hr_document_signers', function (Blueprint $table) {
            $table->dropColumn('sign_order');
        });
    }
};
