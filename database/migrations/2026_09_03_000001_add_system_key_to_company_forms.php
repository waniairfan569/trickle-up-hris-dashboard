<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a company form be designated as the workspace "overtime" form. A form
 * flagged system_key = 'overtime' gets surfaced as a quick "Overtime Approval"
 * button + modal on the Time-Off page. Only one form per workspace should carry
 * it; the builder toggle enforces that. Backfills the flag onto any existing
 * active form whose title mentions overtime so it works without setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_forms', 'system_key')) {
            Schema::table('company_forms', function (Blueprint $table) {
                $table->string('system_key')->nullable()->after('slug')->index();
            });
        }

        // Auto-designate existing overtime form(s) so the feature is live at once.
        $rows = DB::table('company_forms')
            ->whereNull('deleted_at')
            ->whereNull('system_key')
            ->where('status', 'active')
            ->whereRaw('LOWER(title) LIKE ?', ['%overtime%'])
            ->get();

        foreach ($rows as $row) {
            DB::table('company_forms')->where('id', $row->id)->update([
                'system_key' => 'overtime',
                'allow_multiple_submissions' => 1,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_forms', 'system_key')) {
            Schema::table('company_forms', function (Blueprint $table) {
                $table->dropColumn('system_key');
            });
        }
    }
};
