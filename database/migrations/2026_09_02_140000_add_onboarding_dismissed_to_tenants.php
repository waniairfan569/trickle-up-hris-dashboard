<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lets an admin dismiss the "Getting started" setup checklist. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'onboarding_dismissed_at')) {
                $table->timestamp('onboarding_dismissed_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'onboarding_dismissed_at')) {
                $table->dropColumn('onboarding_dismissed_at');
            }
        });
    }
};
