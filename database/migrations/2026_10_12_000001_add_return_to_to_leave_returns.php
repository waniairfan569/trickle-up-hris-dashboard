<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An early return used to be "first day back at work" — always giving back the
 * tail of the leave. It is now a range inside the leave, so any part can be
 * handed back, including a single day in the middle.
 *
 * `return_date` keeps its meaning as the FIRST day given back; `return_to` is
 * the last. Existing rows gave back everything to the end of their leave, so
 * they are backfilled with that leave's end date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_returns') || Schema::hasColumn('leave_returns', 'return_to')) {
            return;
        }

        Schema::table('leave_returns', function (Blueprint $table) {
            $table->date('return_to')->nullable()->after('return_date');
        });

        DB::table('leave_returns')
            ->join('time_off_requests', 'time_off_requests.id', '=', 'leave_returns.time_off_request_id')
            ->whereNull('leave_returns.return_to')
            ->update(['leave_returns.return_to' => DB::raw('time_off_requests.end_date')]);
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_returns') && Schema::hasColumn('leave_returns', 'return_to')) {
            Schema::table('leave_returns', fn (Blueprint $table) => $table->dropColumn('return_to'));
        }
    }
};
