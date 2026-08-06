<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'theme')) {
                $table->string('theme', 20)->default('system')->after('avatar_url');
            }
            if (!Schema::hasColumn('users', 'notification_prefs')) {
                $table->json('notification_prefs')->nullable()->after('theme');
            }
            if (!Schema::hasColumn('users', 'date_format')) {
                $table->string('date_format', 20)->nullable()->after('notification_prefs');
            }
            if (!Schema::hasColumn('users', 'week_start')) {
                $table->string('week_start', 10)->default('monday')->after('date_format');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['theme', 'notification_prefs', 'date_format', 'week_start'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
