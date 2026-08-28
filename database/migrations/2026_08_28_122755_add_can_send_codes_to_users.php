<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A super admin can delegate code-sending to a specific person, so
            // they can answer code requests without being an HR/super admin.
            $table->boolean('can_send_codes')->default(false)->after('is_operator');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_send_codes');
        });
    }
};
