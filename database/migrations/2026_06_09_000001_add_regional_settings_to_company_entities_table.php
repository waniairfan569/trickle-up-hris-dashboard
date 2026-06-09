<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Note: company_entities.timezone already exists, so this migration only
     * adds the display-format columns used by the regional settings UI.
     */
    public function up(): void
    {
        Schema::table('company_entities', function (Blueprint $table) {
            $table->string('date_format')->default('d M Y')->after('timezone');
            $table->string('time_format')->default('h:i A')->after('date_format');
        });
    }

    public function down(): void
    {
        Schema::table('company_entities', function (Blueprint $table) {
            $table->dropColumn(['date_format', 'time_format']);
        });
    }
};
