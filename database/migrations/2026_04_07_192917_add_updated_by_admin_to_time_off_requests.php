<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('updated_by_admin')->nullable()->after('created_by_admin');
            $table->foreign('updated_by_admin')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->dropForeign(['updated_by_admin']);
            $table->dropColumn('updated_by_admin');
        });
    }
};
