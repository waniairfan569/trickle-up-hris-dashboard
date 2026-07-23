<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lateness_deductions', function (Blueprint $table) {
            // When the "3 late arrivals" warning email went out (once per month).
            $table->timestamp('warning_sent_at')->nullable()->after('days_deducted');
        });
    }

    public function down(): void
    {
        Schema::table('lateness_deductions', function (Blueprint $table) {
            $table->dropColumn('warning_sent_at');
        });
    }
};
