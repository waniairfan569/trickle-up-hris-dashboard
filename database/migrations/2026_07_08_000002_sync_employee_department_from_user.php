<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The directory reads employees.department_id; the profile wrote
        // users.department_id. Backfill the Employee rows so departments set via
        // the profile finally show in the directory. Only fill gaps (don't clobber
        // an Employee that already has a department set on its own row).
        if (Schema::hasColumn('employees', 'department_id') && Schema::hasColumn('users', 'department_id')) {
            DB::table('employees')
                ->join('users', 'employees.user_id', '=', 'users.id')
                ->whereNull('employees.department_id')
                ->whereNotNull('users.department_id')
                ->update(['employees.department_id' => DB::raw('users.department_id')]);
        }
    }

    public function down(): void
    {
        // No-op: this only fills previously-empty department references.
    }
};
