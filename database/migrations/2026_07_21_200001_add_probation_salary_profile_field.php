<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a "Probation salary" profile field beside the existing Salary field
     * (Compensation section) for every tenant, so the [Probation Salary] token
     * can auto-fill from the employee's profile.
     */
    public function up(): void
    {
        foreach (DB::table('profile_fields')->where('key', 'salary')->get() as $sal) {
            $exists = DB::table('profile_fields')
                ->where('key', 'probation_salary')
                ->where('section_id', $sal->section_id)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('profile_fields')->insert([
                'tenant_id' => $sal->tenant_id,
                'section_id' => $sal->section_id,
                'name' => 'Probation salary',
                'key' => 'probation_salary',
                'type' => 'currency',
                'options' => null,
                'placeholder' => null,
                'is_required' => 0,
                'is_system' => 0,
                'is_encrypted' => $sal->is_encrypted ?? 0,
                'visibility' => 'internal',
                'employee_can_edit' => 0,
                'sort_order' => ($sal->sort_order ?? 0) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('profile_fields')->where('key', 'probation_salary')->delete();
    }
};
