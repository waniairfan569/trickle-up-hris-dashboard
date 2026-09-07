<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Granular feature access: features (code-defined in App\Support\FeatureCatalog)
 * granted to a custom role (role_feature) and/or directly to an employee
 * (feature_user). Feature keys are stored as strings so the catalog stays the
 * single source of truth in code — no per-feature seed rows to keep in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_feature')) {
            Schema::create('role_feature', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->string('feature_key', 64);
                $table->timestamps();
                $table->unique(['role_id', 'feature_key']);
                $table->index('feature_key');
            });
        }

        if (! Schema::hasTable('feature_user')) {
            Schema::create('feature_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('feature_key', 64);
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'feature_key']);
                $table->index('feature_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_user');
        Schema::dropIfExists('role_feature');
    }
};
