<?php

namespace App\Http\Middleware;

use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Plan feature-gating. A workspace can only reach a module its subscription
 * plan includes. Runs after SetCurrentTenant so the active tenant is known.
 *
 * Gating is declared in ONE place — the MAP below (route-name prefix => plan
 * feature key). To gate another module, add a line here; no route edits needed.
 * A plan whose features include '*' (or the specific key) passes; anything else
 * is sent to the "feature locked" upgrade page (402), or a 402 for JSON/API.
 *
 * Fails OPEN when no tenant can be resolved (pre-SaaS single install, console),
 * so a workspace is never locked out by an unresolved tenant context.
 */
class EnforcePlanFeatures
{
    /**
     * Route-name prefix => required plan feature key. Longest-specific first is
     * not required — prefixes here don't overlap ambiguously (e.g. the report
     * generator's reports.generate / reports.history vs. attendance reports.*).
     */
    public const MAP = [
        'sheets.'            => 'sheets',
        'equipment.'         => 'equipment',
        'code-requests.'     => 'code_requests',
        'reports.generate'   => 'report_generator',
        'reports.history'    => 'report_generator',
        'reports.attendance' => 'reports',
        'reports.index'      => 'reports',
        'attendance-reports.' => 'reports',
        'my-forms.'          => 'forms',
        'company-forms.'     => 'forms',
        'forms.'             => 'forms',
        'company-documents.' => 'esign',
        'hr-documents.'      => 'hr_documents',
        'leave-encashments.' => 'leave_encashment',
        'shifts.'            => 'shifts',
        'probation.'         => 'probation',
        'feedback.'          => 'feedback',
        'performance.'       => 'performance',
        'onboarding.'        => 'onboarding',

        // --- People ---
        // Only the directory listing: employees.profile is the employee's own
        // profile, and gating that would lock someone out of their own record.
        'employees.index'           => 'employee_directory',
        'employees.attendance-mode' => 'attendance_mode',
        'employees.pay-reviews'     => 'pay_reviews',
        'org-chart'                 => 'org_chart',
        'team.'                     => 'team_management',

        // --- Company setup ---
        'departments.'       => 'departments',
        'office-locations.'  => 'office_locations',
        'job-locations.'     => 'office_locations',
        'company-entities.'  => 'company_entities',
        'workspace.branding' => 'branding',
        'roles.'             => 'roles_permissions',
        'admin.sessions'     => 'active_sessions',
        'admin.audit-logs'   => 'audit_logs',
        'zkteco'             => 'zkteco',

        // --- Communication & documents ---
        'events.'              => 'calendar_events',
        'announcements.'       => 'announcements',
        'company-policies.'    => 'policies',
        'my-policies.'         => 'policies',
        'documents-hub.'       => 'documents',
        'document-categories.' => 'documents',
        'document-library.'    => 'documents',
        'documents.'           => 'documents',

        // --- Templates ---
        'profile-templates.'   => 'profile_templates',
        'profile-fields.'      => 'profile_templates',
        'profile-sections.'    => 'profile_templates',
        'signature-templates.' => 'signature_templates',
        'letterheads.'         => 'signature_templates',

        // --- Time & attendance ---
        'company-wfh-days.'       => 'company_wfh',
        'time-tracking-policies.' => 'time_tracking',
        'overtime-report.'        => 'time_tracking',
        'overtime.'               => 'time_tracking',
        'time-off-policies.'      => 'leave',
        'time-off.'               => 'leave',
        'leave-returns.'          => 'leave',
        'leave-year-settings.'    => 'leave',
        // attendance-reports. is mapped to `reports` above and is matched first,
        // so it is not swallowed by the attendance prefixes here.
        'attendance-explanations.' => 'attendance',
        'lateness'                 => 'attendance',
        'attendance.'              => 'attendance',
    ];

    public function __construct(private TenantManager $tenants)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName();
        if (!$name) {
            return $next($request);
        }

        $feature = null;
        foreach (self::MAP as $prefix => $key) {
            if (str_starts_with($name, $prefix)) {
                $feature = $key;
                break;
            }
        }
        if (!$feature) {
            return $next($request);
        }

        $tenant = $this->currentTenant();
        // No resolvable tenant (single-tenant/pre-SaaS install) — don't gate.
        if (!$tenant || $tenant->hasFeature($feature)) {
            return $next($request);
        }

        // Operators are platform staff, never bound by a company's plan.
        $user = $request->user();
        if ($user && method_exists($user, 'isOperator') && $user->isOperator()) {
            return $next($request);
        }

        $label = PlanFeature::labels()[$feature] ?? ucfirst(str_replace('_', ' ', $feature));

        if ($request->expectsJson()) {
            abort(402, $label . ' is not included in your current plan.');
        }

        $canManage = $user
            && method_exists($user, 'hasRole')
            && ($user->hasRole('super-admin') || $user->hasRole('admin'));

        return response()->view('billing.feature-locked', [
            'feature'   => $feature,
            'label'     => $label,
            'planName'  => $tenant->planConfig()['name'] ?? ucfirst((string) $tenant->plan),
            'canManage' => $canManage,
        ], 402);
    }

    private function currentTenant(): ?Tenant
    {
        if ($current = $this->tenants->get()) {
            return $current;
        }

        $user = auth()->user();
        if ($user && $user->tenant_id) {
            return Tenant::find($user->tenant_id);
        }

        return Tenant::query()->count() === 1 ? Tenant::query()->first() : null;
    }
}
