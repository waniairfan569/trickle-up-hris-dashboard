<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('time_off_requests', 'duration_type')) {
                $table->enum('duration_type', ['full_day', 'half_day', 'hourly'])
                    ->default('full_day')->after('days_requested');
            }
            if (!Schema::hasColumn('time_off_requests', 'hours_requested')) {
                $table->decimal('hours_requested', 5, 2)->nullable()->after('duration_type');
            }
            if (!Schema::hasColumn('time_off_requests', 'start_time')) {
                $table->time('start_time')->nullable()->after('hours_requested');
            }
            if (!Schema::hasColumn('time_off_requests', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
            // Widen so hourly day-equivalents (e.g. 0.25) fit.
            $table->decimal('days_requested', 6, 2)->change();
        });

        // Existing half-day rows -> duration_type 'half_day'.
        DB::table('time_off_requests')->where('is_half_day', true)->update(['duration_type' => 'half_day']);

        // Balances are kept in day-equivalents; widen to 2 decimals for hourly.
        Schema::table('time_off_balances', function (Blueprint $table) {
            foreach (['opening_balance', 'accrued', 'used', 'pending', 'carried_over', 'adjusted'] as $col) {
                if (Schema::hasColumn('time_off_balances', $col)) {
                    $table->decimal($col, 6, 2)->default(0)->change();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            foreach (['duration_type', 'hours_requested', 'start_time', 'end_time'] as $col) {
                if (Schema::hasColumn('time_off_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
            $table->decimal('days_requested', 5, 1)->change();
        });

        Schema::table('time_off_balances', function (Blueprint $table) {
            foreach (['opening_balance', 'accrued', 'used', 'pending', 'carried_over', 'adjusted'] as $col) {
                if (Schema::hasColumn('time_off_balances', $col)) {
                    $table->decimal($col, 5, 1)->default(0)->change();
                }
            }
        });
    }
};
