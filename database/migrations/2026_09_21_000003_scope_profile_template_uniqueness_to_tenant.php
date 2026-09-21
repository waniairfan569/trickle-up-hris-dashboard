<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile templates/fields are per-tenant (BelongsToTenant), but slug/key were
 * still GLOBALLY unique — so a second tenant could never own its own copy of the
 * "Default Employee Profile" (the slug/key would collide). That's why new
 * workspaces had no profile template at all. Re-scope the uniqueness to the
 * tenant so every workspace can hold the same default independently.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('profile_templates', function (Blueprint $table) {
            $table->dropUnique('profile_templates_slug_unique');
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('profile_fields', function (Blueprint $table) {
            $table->dropUnique('profile_fields_key_unique');
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('profile_templates', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
        });

        Schema::table('profile_fields', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->unique('key');
        });
    }
};
