<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Note: users.timezone already exists (added with profile fields).
     * Here we only add the toggle that decides whether that per-user timezone
     * overrides the company entity timezone. When use_custom_timezone is false,
     * the employee inherits the company entity's timezone regardless of the
     * stored timezone value.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 100)->nullable()->after('country');
            }
            if (!Schema::hasColumn('users', 'use_custom_timezone')) {
                $table->boolean('use_custom_timezone')->default(false)->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'use_custom_timezone')) {
                $table->dropColumn('use_custom_timezone');
            }
        });
    }
};
