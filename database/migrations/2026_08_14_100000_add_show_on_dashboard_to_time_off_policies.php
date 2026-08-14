<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_policies', function (Blueprint $table) {
            // Whether this policy's balance card shows on the employee dashboard's
            // "Your time-off balances" section. Independent of assignment — default
            // on so existing policies keep showing.
            if (!Schema::hasColumn('time_off_policies', 'show_on_dashboard')) {
                $table->boolean('show_on_dashboard')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_off_policies', function (Blueprint $table) {
            if (Schema::hasColumn('time_off_policies', 'show_on_dashboard')) {
                $table->dropColumn('show_on_dashboard');
            }
        });
    }
};
