<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a leaver's balance was settled, and by whom.
 *
 * Settling writes an adjustment that brings a year's entitlement down to what
 * the person actually earned by their last day. Doing that twice would take
 * the same days off them twice, and the balance alone cannot tell whether an
 * adjustment already includes a settlement — so it is recorded here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_balances', function (Blueprint $table) {
            $table->date('settled_to')->nullable()->after('adjusted');
            // What the settlement itself wrote, so it can be read back out of
            // the adjustment — otherwise a settled balance reports as though
            // its own correction were days the person had earned.
            $table->decimal('settled_delta', 6, 2)->nullable()->after('settled_to');
            $table->timestamp('settled_at')->nullable()->after('settled_to');
            $table->foreignId('settled_by')->nullable()->after('settled_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('time_off_balances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn(['settled_to', 'settled_delta', 'settled_at']);
        });
    }
};
