<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every user was seeded with the stale default timezone 'UTC'. It's currently
     * masked (use_custom_timezone = false → effective zone comes from the company
     * entity), but it's a landmine: toggling a custom timezone would suddenly show
     * UTC. Normalise the stale value to the org's actual zone.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'timezone')) {
            return;
        }

        DB::table('users')
            ->where(fn ($q) => $q->where('timezone', 'UTC')->orWhereNull('timezone'))
            ->update(['timezone' => 'Asia/Karachi']);
    }

    public function down(): void
    {
        // No-op: we don't want to restore a known-bad default.
    }
};
