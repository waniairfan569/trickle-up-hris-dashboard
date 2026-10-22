<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a workspace decide, per template, whether a letter goes out on its own.
 *
 * Every auto-generated document has stopped at draft, because sending asks two
 * questions nobody can answer in the middle of the night: what happens to the
 * manager's signature box, and — if the sender signs it — which signature to
 * stamp. Answering them once on the template turns that into a setting rather
 * than a nightly chore.
 *
 * Off by default, and off for every template that already exists. A workspace
 * that wants a return-to-work form to send itself can say so; a workspace that
 * wants a human to read every probation letter changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_document_templates', function (Blueprint $table) {
            $table->boolean('auto_send')->default(false)->after('rollup');

            // What to do with the manager section when it sends itself:
            // 'none' leaves it blank, 'manager' asks the line manager to sign,
            // 'self' stamps a saved signature before it goes out.
            $table->string('auto_send_manager_mode', 16)->default('none')->after('auto_send');

            $table->foreignId('auto_send_signature_id')->nullable()->after('auto_send_manager_mode')
                ->constrained('signature_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_document_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auto_send_signature_id');
            $table->dropColumn(['auto_send', 'auto_send_manager_mode']);
        });
    }
};
