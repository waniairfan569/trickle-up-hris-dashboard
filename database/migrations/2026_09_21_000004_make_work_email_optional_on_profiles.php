<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Work email is no longer required to create an employee (a new hire often has
 * no company mailbox yet — the personal email is the sign-in address until one
 * is added). Drop the "required" flag on the work_email profile field for every
 * tenant so the form matches the new create rules.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('profile_fields')->where('key', 'work_email')->update(['is_required' => false]);
    }

    public function down(): void
    {
        DB::table('profile_fields')->where('key', 'work_email')->update(['is_required' => true]);
    }
};
