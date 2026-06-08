<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('annual_leave_days', 4, 1)->default(20.0);
            $table->decimal('sick_leave_days', 4, 1)->default(10.0);
            $table->decimal('used_annual_days', 4, 1)->default(0.0);
            $table->decimal('used_sick_days', 4, 1)->default(0.0);
            $table->decimal('remaining_annual_days', 4, 1)->default(20.0);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'annual_leave_days',
                'sick_leave_days',
                'used_annual_days',
                'used_sick_days',
                'remaining_annual_days',
            ]);
        });
    }
};
