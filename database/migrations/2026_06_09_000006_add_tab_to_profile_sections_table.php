<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Groups profile sections into Workable-style profile tabs
     * (personal, job, compensation, legal, experience, emergency).
     */
    public function up(): void
    {
        Schema::table('profile_sections', function (Blueprint $table) {
            $table->string('tab')->default('personal')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('profile_sections', function (Blueprint $table) {
            $table->dropColumn('tab');
        });
    }
};
