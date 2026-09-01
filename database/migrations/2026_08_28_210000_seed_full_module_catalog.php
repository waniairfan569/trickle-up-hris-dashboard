<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand the module catalog to cover the app's real modules (not just the 9
     * coarse features). Existing keys are kept so current plans still resolve;
     * only missing modules are added.
     */
    public function up(): void
    {
        // key => label. Existing keys (attendance, leave, documents, policies,
        // announcements, esign, forms, reports, probation) are skipped if present.
        $catalog = [
            'attendance'          => 'Attendance & clock-in',
            'attendance_mode'     => 'Attendance modes (hybrid / WFH)',
            'shifts'              => 'Shift management',
            'time_tracking'       => 'Time-tracking policies',
            'company_wfh'         => 'Company WFH days',
            'reports'             => 'Attendance reports',
            'report_generator'    => 'Report generator',
            'leave'               => 'Time off & policies',
            'leave_encashment'    => 'Leave encashment',
            'calendar_events'     => 'Calendar & events',
            'announcements'       => 'Announcements',
            'feedback'            => 'Feedback & suggestions',
            'forms'               => 'Custom forms',
            'policies'            => 'Company policies',
            'documents'           => 'Document library',
            'esign'               => 'E-signature documents',
            'hr_documents'        => 'HR documents',
            'sheets'              => 'Sheets library',
            'equipment'           => 'Equipment requests',
            'code_requests'       => 'Code requests',
            'employee_directory'  => 'Employees directory',
            'org_chart'           => 'Org chart',
            'team_management'     => 'Team management (live board)',
            'probation'           => 'Probation tracking',
            'performance'         => 'Performance reviews',
            'onboarding'          => 'Onboarding',
            'recruiting'          => 'Recruiting',
            'pay_reviews'         => 'Pay reviews / compensation',
            'profile_templates'   => 'Profile templates',
            'signature_templates' => 'Signature templates',
            'departments'         => 'Departments',
            'office_locations'    => 'Office locations',
            'company_entities'    => 'Company entities',
            'branding'            => 'Workspace branding',
            'roles_permissions'   => 'Roles & permissions',
            'active_sessions'     => 'Active sessions',
            'audit_logs'          => 'Audit logs',
            'zkteco'              => 'Biometric devices (ZKTeco)',
        ];

        $existing = DB::table('plan_features')->pluck('key')->all();
        $sort = (int) (DB::table('plan_features')->max('sort_order') ?? -1);

        foreach ($catalog as $key => $label) {
            if (in_array($key, $existing, true)) {
                continue; // keep what's already there (plans reference it)
            }
            DB::table('plan_features')->insert([
                'key'        => $key,
                'label'      => $label,
                'sort_order' => ++$sort,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Re-order the whole catalog to the intended sequence for a tidy list.
        $order = 0;
        foreach (array_keys($catalog) as $key) {
            DB::table('plan_features')->where('key', $key)->update(['sort_order' => $order++]);
        }
    }

    public function down(): void
    {
        // Non-destructive: leave the catalog as-is on rollback.
    }
};
