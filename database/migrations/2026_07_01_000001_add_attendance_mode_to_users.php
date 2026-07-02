<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'attendance_mode')) {
                // biometric = must use the ZKTeco device (dashboard clock-in hidden)
                // remote    = can clock in/out from the dashboard
                $table->enum('attendance_mode', ['biometric', 'remote'])
                    ->default('biometric')
                    ->after('account_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'attendance_mode')) {
                $table->dropColumn('attendance_mode');
            }
        });
    }
};
