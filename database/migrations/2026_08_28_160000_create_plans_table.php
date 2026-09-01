<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();            // stable identifier stored on tenants.plan
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('interval')->default('monthly'); // monthly | yearly
            $table->unsignedInteger('seats')->default(0);    // 0 = unlimited
            $table->json('features')->nullable();            // ['*'] = everything
            $table->unsignedInteger('trial_days')->default(0);
            $table->text('blurb')->nullable();
            $table->boolean('is_public')->default(true);     // shown on pricing / selectable by customers
            $table->boolean('is_active')->default(true);     // available to assign (archive = false)
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('stripe_price_id')->nullable();   // for later Stripe wiring
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed the DB from the existing static config so nothing breaks.
        $i = 0;
        foreach ((array) config('plans.plans', []) as $key => $p) {
            DB::table('plans')->insert([
                'key'             => $key,
                'name'            => $p['name'] ?? ucfirst($key),
                'price'           => $p['price'] ?? 0,
                'currency'        => config('plans.currency', 'USD'),
                'interval'        => 'monthly',
                'seats'           => $p['seats'] ?? 0,
                'features'        => json_encode($p['features'] ?? []),
                'trial_days'      => $key === 'trial' ? 14 : 0,
                'blurb'           => $p['blurb'] ?? null,
                'is_public'       => (bool) ($p['selectable'] ?? false),
                'is_active'       => true,
                'sort_order'      => $i++,
                'stripe_price_id' => $p['stripe_price'] ?? null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
