<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin', 'admin', 'manager', 'interviewer',
                'recruiting_admin', 'hiring_manager', 
                'contributor', 'reviewer', 'employee', 'external_recruiter'
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin', 'recruiting_admin', 'hiring_manager', 
                'contributor', 'reviewer', 'employee', 'external_recruiter'
            ])->change();
        });
    }
};
