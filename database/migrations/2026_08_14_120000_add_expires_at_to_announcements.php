<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements') || Schema::hasColumn('announcements', 'expires_at')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            // Optional auto-hide date: past this day the announcement stops
            // showing on dashboards. Null = never expires.
            $table->date('expires_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('announcements', 'expires_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
