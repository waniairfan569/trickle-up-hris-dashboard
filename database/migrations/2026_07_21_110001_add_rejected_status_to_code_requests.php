<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins can now decline a code request (not just fulfil it). Widen the
     * status enum and add an optional reason shown back to the employee.
     */
    public function up(): void
    {
        Schema::table('code_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('code_requests', 'rejection_reason')) {
                $table->string('rejection_reason')->nullable()->after('code_expires_note');
            }
        });

        // Widen the ENUM to allow 'rejected' (MySQL).
        try {
            DB::statement("ALTER TABLE code_requests MODIFY status ENUM('pending','code_sent','rejected') NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // Non-MySQL / already-widened — safe to ignore.
        }
    }

    public function down(): void
    {
        Schema::table('code_requests', function (Blueprint $table) {
            if (Schema::hasColumn('code_requests', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });

        try {
            DB::statement("ALTER TABLE code_requests MODIFY status ENUM('pending','code_sent') NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
