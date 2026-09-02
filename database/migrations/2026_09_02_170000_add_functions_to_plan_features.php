<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make a module's functions/sub-features editable + persisted, instead of a
 * static code map. Backfills the built-in modules from App\Support\ModuleCatalog
 * so nothing is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_features', 'functions')) {
                $table->json('functions')->nullable()->after('description');
            }
        });

        // Seed the built-in modules' functions (only where empty).
        foreach (DB::table('plan_features')->get() as $pf) {
            if (!empty($pf->functions)) {
                continue;
            }
            $fns = \App\Support\ModuleCatalog::functions($pf->key);
            if ($fns) {
                DB::table('plan_features')->where('id', $pf->id)
                    ->update(['functions' => json_encode(array_values($fns))]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            if (Schema::hasColumn('plan_features', 'functions')) {
                $table->dropColumn('functions');
            }
        });
    }
};
