<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();      // the stable gate key stored in plans.features
            $table->string('label');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the catalog from the current static config so nothing changes.
        $i = 0;
        foreach ((array) config('plans.feature_labels', []) as $key => $label) {
            DB::table('plan_features')->insert([
                'key'        => $key,
                'label'      => $label,
                'sort_order' => $i++,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
