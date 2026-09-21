<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A short "tell us about your company" questionnaire shown once to a brand-new
 * workspace owner (company size, industry, country, how they heard about us).
 * The answers live on the tenant; `onboarding_survey_at` marks it answered/skipped
 * so the popup never shows twice. Existing tenants are back-filled as already-done.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('company_size')->nullable()->after('currency');
            $table->string('industry')->nullable()->after('company_size');
            $table->string('country')->nullable()->after('industry');
            $table->string('heard_from')->nullable()->after('country');
            $table->timestamp('onboarding_survey_at')->nullable()->after('heard_from');
        });

        // Don't nag companies that already exist — only new signups see the popup.
        DB::table('tenants')->whereNull('onboarding_survey_at')->update(['onboarding_survey_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['company_size', 'industry', 'country', 'heard_from', 'onboarding_survey_at']);
        });
    }
};
