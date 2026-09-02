<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe billing identifiers on the workspace (tenant). The workspace is the
 * billable entity — one Stripe customer + subscription per company. Added
 * idempotently so re-running on any environment is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('plan')->index();
            }
            if (!Schema::hasColumn('tenants', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id')->index();
            }
            if (!Schema::hasColumn('tenants', 'card_brand')) {
                $table->string('card_brand')->nullable()->after('stripe_subscription_id');
            }
            if (!Schema::hasColumn('tenants', 'card_last_four')) {
                $table->string('card_last_four', 4)->nullable()->after('card_brand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['stripe_customer_id', 'stripe_subscription_id', 'card_brand', 'card_last_four'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
