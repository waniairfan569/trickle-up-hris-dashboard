<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Employees can now cancel a code request they no longer need. Widen the
     * status enum to allow 'cancelled'.
     */
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE code_requests MODIFY status ENUM('pending','code_sent','rejected','cancelled') NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // Non-MySQL / already-widened — safe to ignore.
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE code_requests MODIFY status ENUM('pending','code_sent','rejected') NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
