<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->enum('source', ['web', 'zkteco', 'excel_import', 'manual'])->default('web')->after('notes');
            $table->foreignId('zkteco_punch_id')->nullable()->constrained('zkteco_raw_punches')->nullOnDelete()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_records', 'zkteco_punch_id')) {
                $table->dropForeign(['zkteco_punch_id']);
                $table->dropColumn(['source', 'zkteco_punch_id']);
            } elseif (Schema::hasColumn('attendance_records', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
