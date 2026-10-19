<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thirty-eight modules in one flat list is hard to read and harder to price:
 * nothing shows that clock-in, attendance modes and shift management are the
 * same part of the product. Give each module a parent group so the catalog,
 * the plan builder and a company's module list all read as sections.
 */
return new class extends Migration
{
    /** group => the modules that belong under it */
    private const GROUPS = [
        'Time & attendance'  => ['attendance', 'attendance_mode', 'shifts', 'time_tracking', 'company_wfh', 'zkteco'],
        'Leave'              => ['leave', 'leave_encashment'],
        'People'             => ['employee_directory', 'org_chart', 'team_management', 'probation', 'performance', 'onboarding', 'recruiting', 'pay_reviews', 'profile_templates'],
        'Documents & forms'  => ['documents', 'forms', 'policies', 'esign', 'hr_documents', 'signature_templates'],
        'Communication'      => ['announcements', 'feedback', 'calendar_events'],
        'Reports'            => ['reports', 'report_generator'],
        'Company setup'      => ['departments', 'office_locations', 'company_entities', 'branding', 'roles_permissions'],
        'Security & audit'   => ['active_sessions', 'audit_logs'],
        'Assets & tools'     => ['equipment', 'sheets', 'code_requests'],
    ];

    public function up(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            $table->string('group')->nullable()->after('label');
        });

        // Sort so a group's modules sit together in the order listed above,
        // and the groups themselves keep the order of the array.
        $position = 0;
        foreach (self::GROUPS as $group => $keys) {
            foreach ($keys as $key) {
                DB::table('plan_features')->where('key', $key)->update([
                    'group'      => $group,
                    'sort_order' => ++$position,
                ]);
            }
        }

        // Anything added since, or not in the map, lands in a catch-all rather
        // than disappearing from a grouped view.
        DB::table('plan_features')->whereNull('group')->update(['group' => 'Other']);
    }

    public function down(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }
};
