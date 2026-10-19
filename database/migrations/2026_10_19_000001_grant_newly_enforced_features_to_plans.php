<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Most of the switches on the Modules page did nothing: the keys were listed in
 * plan_features but no code ever asked whether they were on, so turning one off
 * changed nothing. EnforcePlanFeatures now gates them.
 *
 * Enforcing a switch that a plan never listed would silently take working pages
 * away from that plan's customers — they had the employee directory yesterday
 * and would lose it on deploy. So grant every plan the modules it could already
 * reach. Nothing changes for anyone today; narrowing a tier becomes a decision
 * someone makes on the Modules page, rather than a side effect of this fix.
 */
return new class extends Migration
{
    /** Keys that had no gate before and do now. */
    private const NEWLY_ENFORCED = [
        'employee_directory', 'attendance_mode', 'pay_reviews', 'org_chart',
        'team_management', 'departments', 'office_locations', 'company_entities',
        'branding', 'roles_permissions', 'active_sessions', 'audit_logs',
        'zkteco', 'calendar_events', 'profile_templates', 'signature_templates',
        'company_wfh', 'time_tracking', 'attendance', 'leave', 'documents',
        'policies', 'announcements',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        foreach (DB::table('plans')->get(['id', 'features']) as $plan) {
            $features = json_decode((string) $plan->features, true);
            if (! is_array($features) || in_array('*', $features, true)) {
                continue;   // already grants everything
            }

            $merged = array_values(array_unique(array_merge($features, self::NEWLY_ENFORCED)));
            if (count($merged) === count($features)) {
                continue;
            }

            DB::table('plans')->where('id', $plan->id)
                ->update(['features' => json_encode($merged)]);
        }
    }

    public function down(): void
    {
        // Leaves the grants in place. Removing them would take access away from
        // plans that had it before this migration ran.
    }
};
