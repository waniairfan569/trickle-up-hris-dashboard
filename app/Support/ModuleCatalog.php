<?php

namespace App\Support;

/**
 * The specific functions/sub-features each built-in module contains — so the
 * operator Modules page can show what a module actually gates, not just its
 * name. Keyed by the plan-feature key. Operator-added custom modules simply
 * have no entry here (that's fine).
 */
class ModuleCatalog
{
    public static function functions(string $key): array
    {
        return self::MAP[$key] ?? [];
    }

    private const MAP = [
        'attendance' => ['Clock in / out + breaks', 'Monthly attendance calendar', 'Late / overtime / early-leave tracking', 'Correction requests'],
        'attendance_mode' => ['On-site / biometric mode', 'Remote & hybrid (WFH) modes', 'Per-employee remote days'],
        'shifts' => ['Create shift patterns', 'Assign shifts to people/teams', 'Default shift auto-assign', 'My schedule'],
        'time_tracking' => ['Time-tracking policies', 'Clock-in/out reminders', 'Working-hours rules'],
        'company_wfh' => ['Company-wide WFH days', 'Bulk remote scheduling'],
        'reports' => ['Attendance reports', 'Scheduled daily report email', 'Manual send + preview', 'Report settings'],
        'report_generator' => ['Build custom exports by date range', 'Preview & download', 'Generation history + re-download'],
        'leave' => ['Request full/half-day & hourly leave', 'Approval workflow', 'Leave policies & allowances', 'Balances & carry-over'],
        'leave_encashment' => ['Encashment records', 'Mark paid', 'Encashment on leave-year renewal'],
        'calendar_events' => ['Company calendar', 'Single & multi-day events', 'Online events with a Join link'],
        'announcements' => ['Post announcements', 'Dashboard + email delivery', 'Unread tracking'],
        'feedback' => ['Submit feedback/suggestions', 'Admin triage & respond', 'Status tracking'],
        'forms' => ['Custom form builder', 'Assign forms to people', 'Reviewers & submissions', 'Responses & export'],
        'policies' => ['Publish company policies', 'Require acknowledgement', 'Assignment & tracking'],
        'documents' => ['Document library', 'Share with staff', 'Acknowledgement + views'],
        'esign' => ['Send documents for e-signature', 'Place signature fields', 'Track signer status', 'Signed-file storage'],
        'hr_documents' => ['Templated HR documents (offers, contracts)', 'Generate per employee', 'Sign + store', 'To-sign queue'],
        'sheets' => ['Linked Google Sheets library', 'Named links + preview', 'Admin-managed'],
        'equipment' => ['Employee equipment requests', 'Admin approve/reject', 'Export'],
        'code_requests' => ['Request a tool login/OTP code', 'HR shares securely', 'Reveal + resend', 'Auto-redaction'],
        'employee_directory' => ['Browse employees', 'Profiles, roles, departments', 'Scoped by permission'],
        'org_chart' => ['Reporting-line hierarchy', 'Grouped & flat views'],
        'team_management' => ['Live board — in / out / remote / on leave', 'Team attendance', 'Pending corrections'],
        'probation' => ['Track probation periods', 'Overdue-review reminders', 'Confirm / fail outcomes'],
        'performance' => ['Review cycles', 'Self & manager reviews', 'Share, sign, reopen'],
        'onboarding' => ['Onboarding workflows & tasks', 'Assign to new hires', 'Track completion'],
        'recruiting' => ['Jobs & candidates', 'Pipeline stages', 'Offers & interviews'],
        'pay_reviews' => ['Pay / compensation reviews', 'Salary change tracking'],
        'profile_templates' => ['Custom profile sections & fields', 'Assign templates to people'],
        'signature_templates' => ['Reusable signature blocks', 'Apply to documents'],
        'departments' => ['Create departments', 'Assign people & managers'],
        'office_locations' => ['Office locations', 'Assign staff to a location'],
        'company_entities' => ['Company entities / legal orgs', 'General company settings'],
        'branding' => ['Workspace logo & colour', 'From-name / email branding'],
        'roles_permissions' => ['Built-in roles', 'Edit per-role permissions', 'Access follows the plan'],
        'active_sessions' => ['See your active sessions', 'Sign out other devices', 'Admin force-logout'],
        'audit_logs' => ['System activity log', 'Who did what, when'],
        'zkteco' => ['ZKTeco biometric devices', 'Live punch sync', 'Device management'],
    ];
}
