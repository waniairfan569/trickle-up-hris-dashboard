<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tenant-owned tables that get scoped per agency. */
    private array $tables = [
        'users', 'employees', 'departments', 'user_department_assignments',
        'time_off_requests', 'time_off_policies', 'time_off_balances', 'time_off_audit_logs',
        'leave_balances', 'policy_assignments',
        'attendance_records', 'attendance_corrections', 'attendance_settings', 'attendance_import_logs',
        'attendance_report_logs', 'break_records',
        'company_documents', 'document_categories', 'document_access', 'document_views',
        'company_forms', 'form_fields', 'form_assignments', 'form_submissions', 'form_responses',
        'company_policies', 'policy_acknowledgments',
        'document_templates', 'document_template_fields', 'document_template_signers',
        'document_requests', 'document_request_signers', 'document_request_events', 'form_reviewers',
        'announcements', 'events', 'holidays', 'holiday_calendars', 'public_holidays',
        'work_schedules', 'shifts', 'shift_assignments', 'office_locations', 'job_locations', 'locations',
        'code_requests', 'probations', 'probation_events', 'lateness_deductions',
        'performance_reviews', 'review_cycles', 'pay_reviews',
        'onboarding_workflows', 'onboarding_tasks', 'onboarding_task_templates', 'employee_onboardings',
        'profile_templates', 'profile_sections', 'profile_fields', 'employee_field_values',
        'employee_documents', 'company_files', 'company_entities', 'companies',
        'zkteco_devices', 'zkteco_raw_punches', 'zkteco_unmappeds',
        'activity_logs', 'invitation_logs', 'code_requests',
    ];

    public function up(): void
    {
        $defaultId = DB::table('tenants')->where('slug', 'trickle-up')->value('id');

        foreach (array_unique($this->tables) as $table) {
            try {
                if (!Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
                });
                if ($defaultId) {
                    DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultId]);
                }
            } catch (\Throwable $e) {
                // Skip tables that can't be altered (e.g. a locally-broken table);
                // don't let one table halt the whole migration.
                report($e);
            }
        }
    }

    public function down(): void
    {
        foreach (array_unique($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('tenant_id');
                });
            }
        }
    }
};
