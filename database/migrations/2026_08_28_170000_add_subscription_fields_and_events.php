<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('plan'); // 0-100
            $table->timestamp('canceled_at')->nullable()->after('trial_ends_at');
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');            // plan_changed | canceled | reactivated | trial_extended | discount_applied | suspended | activated
            $table->string('description');
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'canceled_at']);
        });
    }
};
