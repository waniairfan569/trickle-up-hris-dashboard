<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly (recurring) company forms: a form can be re-opened every month, and
 * each month's submission is kept separately as history. `period` is 'YYYY-MM'
 * (null for one-off forms — existing behaviour is unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_forms', function (Blueprint $table) {
            $table->boolean('is_monthly')->default(false)->after('allow_multiple_submissions');
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('period', 7)->nullable()->after('assignment_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('company_forms', function (Blueprint $table) {
            $table->dropColumn('is_monthly');
        });
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('period');
        });
    }
};
