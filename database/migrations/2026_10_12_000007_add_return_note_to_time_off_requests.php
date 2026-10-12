<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employee's own note on coming back from an unplanned absence, written
 * from their portal instead of a return-to-work meeting. It belongs to the
 * one leave it is about, so it lives on the request rather than in a table
 * of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->text('return_note')->nullable()->after('reason');
            $table->timestamp('return_note_at')->nullable()->after('return_note');
            $table->string('return_note_ip', 45)->nullable()->after('return_note_at');
        });
    }

    public function down(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->dropColumn(['return_note', 'return_note_at', 'return_note_ip']);
        });
    }
};
