<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a policy charges leave taken by the hour.
 *
 *   band   two hours up to half a day costs half a day, as it always has
 *   exact  charged by the hour, against the length of the working day
 *
 * The band was written into the code with one company's workday in mind. It
 * makes two hours and four hours cost the same, while one minute less than two
 * hours costs less than half as much — a cliff nobody chose and no client could
 * change.
 *
 * Defaults to "band" everywhere, so no workspace's existing leave changes
 * meaning until somebody picks the other one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_policies', function (Blueprint $table) {
            $table->string('hourly_charge_mode', 10)->default('band')->after('allow_half_days');
        });
    }

    public function down(): void
    {
        Schema::table('time_off_policies', function (Blueprint $table) {
            $table->dropColumn('hourly_charge_mode');
        });
    }
};
