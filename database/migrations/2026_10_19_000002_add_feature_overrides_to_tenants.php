<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company exceptions to the plan's module list, so one workspace can be
 * given an extra module or have one taken away without inventing a plan for
 * them. Held as the differences rather than a frozen copy of the whole list,
 * so a later plan change still reaches the company and only the deliberate
 * exceptions survive it.
 *
 * Shape: {"on": ["shifts"], "off": ["sheets"]}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('feature_overrides')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('feature_overrides');
        });
    }
};
