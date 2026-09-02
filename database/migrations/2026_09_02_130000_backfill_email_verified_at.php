<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grandfather every existing user as email-verified. Verification is only
 * required for public self-signup going forward (see User + RegisterTenant),
 * so no current account — invited, seeded, or admin-created — is ever locked
 * out when the `verified` gate is enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, NOW())')]);
    }

    public function down(): void
    {
        // Non-reversible: we can't know which users were originally unverified.
    }
};
