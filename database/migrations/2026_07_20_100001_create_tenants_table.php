<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subdomain')->nullable()->unique();
            $table->string('status')->default('active');          // active | suspended | trialing
            $table->string('plan')->default('free');
            // White-label / config (per-agency branding)
            $table->string('brand_name')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('from_email')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        // Seed the default tenant for all existing data (the current org).
        if (!DB::table('tenants')->where('slug', 'trickle-up')->exists()) {
            DB::table('tenants')->insert([
                'name' => 'Trickle Up',
                'slug' => 'trickle-up',
                'subdomain' => 'app',
                'status' => 'active',
                'plan' => 'internal',
                'brand_name' => 'Trickle Hub',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
