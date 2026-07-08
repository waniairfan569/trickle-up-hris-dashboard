<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'is_system')) {
                // System/owner accounts (e.g. the company super-admin) can log in
                // but must NOT appear as employees in directory/attendance/reports.
                $table->boolean('is_system')->default(false)->after('user_id');
            }
        });

        // Flag the owner/system super-admin account so it's hidden from employee lists.
        DB::table('employees')->where('user_id', 1)->update(['is_system' => true]);
        DB::table('employees')->where('email', 'admin@company.com')->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'is_system')) {
                $table->dropColumn('is_system');
            }
        });
    }
};
