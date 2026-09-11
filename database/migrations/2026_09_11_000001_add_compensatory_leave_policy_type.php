<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compensation leave (time off in lieu): a new `compensatory` policy type whose
 * balance is never granted up-front — HR credits days against overtime worked,
 * and the employee requests leave against those credits like any other policy.
 *
 * Seeds a "Compensation Leave" policy into every workspace that has policies
 * but no compensatory one yet. It is NOT auto-assigned: the first credit
 * attaches it to the employee, so nobody sees an empty 0-day card.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE time_off_policies MODIFY type ENUM('annual','sick','unpaid','maternity','paternity','bereavement','compensatory','custom') NOT NULL");
        } catch (\Throwable $e) {
            // Non-MySQL / already widened — safe to ignore.
        }

        $tenantIds = DB::table('time_off_policies')->whereNull('deleted_at')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $exists = DB::table('time_off_policies')
                ->where('tenant_id', $tenantId)->where('type', 'compensatory')->whereNull('deleted_at')->exists();
            if ($exists) {
                continue;
            }

            DB::table('time_off_policies')->insert([
                'tenant_id' => $tenantId,
                'name' => 'Compensation Leave',
                'description' => 'Time off in lieu of overtime. Days are credited by HR for overtime worked and can be taken as paid leave.',
                'type' => 'compensatory',
                'accrual_type' => 'none',
                'days_per_year' => 0,
                'max_balance' => null,
                'carry_over' => 0,
                'carry_over_max' => null,
                'requires_approval' => 1,
                'approval_type' => 'manager',
                'min_notice_days' => 0,
                'allow_half_days' => 1,
                'allow_negative_balance' => 0,
                'is_paid' => 1,
                'is_active' => 1,
                'show_on_dashboard' => 1,
                'auto_assign_to_new_employees' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('time_off_policies')->where('type', 'compensatory')->where('name', 'Compensation Leave')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('time_off_requests')->whereColumn('time_off_requests.policy_id', 'time_off_policies.id'))
            ->delete();

        try {
            DB::statement("UPDATE time_off_policies SET type = 'custom' WHERE type = 'compensatory'");
            DB::statement("ALTER TABLE time_off_policies MODIFY type ENUM('annual','sick','unpaid','maternity','paternity','bereavement','custom') NOT NULL");
        } catch (\Throwable $e) {
            //
        }
    }
};
