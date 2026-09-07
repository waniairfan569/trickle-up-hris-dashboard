<?php

namespace App\Support;

/**
 * The catalog of grantable features — the admin-delegatable capabilities a
 * super admin can hand to a custom role or to an individual employee.
 *
 * Deliberately EXCLUDED (confidential / never grantable here):
 *  - the employee profile "Information" tab and its field data (its own
 *    visibility/encryption subsystem governs that), and
 *  - Compensation / pay (salary, pay reviews), and
 *  - role & permission management itself (super-admin only, so a granted
 *    user can never escalate or hand out access).
 *
 * Each feature optionally names a plan feature key: even when access is
 * granted, the workspace plan must still include that module (plan gating
 * via plan_allows() continues to apply on top of this).
 */
class FeatureCatalog
{
    /**
     * group label => [ feature key => [label, plan?, routes?] ]
     * `plan`   — plan feature key that must also be enabled (null = always on)
     * `routes` — route-name prefixes the `feature:` middleware protects
     */
    private const GROUPS = [
        'People & teams' => [
            'employee_directory' => ['label' => 'Employee directory (view everyone)', 'plan' => 'employee_directory', 'routes' => ['employees.index']],
            'org_chart'          => ['label' => 'Org chart',                           'plan' => 'org_chart',          'routes' => ['org-chart']],
            'team_management'    => ['label' => 'Team management',                     'plan' => 'team_management',    'routes' => ['team.']],
            'onboarding'         => ['label' => 'Onboarding',                          'plan' => 'onboarding',         'routes' => ['onboarding.']],
            'probation'          => ['label' => 'Probation reviews',                   'plan' => 'probation',          'routes' => ['probation.']],
            'performance'        => ['label' => 'Performance reviews',                 'plan' => 'performance',        'routes' => ['performance.']],
        ],
        'Time & attendance' => [
            'time_off_admin'     => ['label' => 'Time-off approvals & requests',       'plan' => 'leave',              'routes' => []],
            'time_off_policies'  => ['label' => 'Time-off policies',                   'plan' => 'leave',              'routes' => ['time-off-policies.']],
            'attendance_admin'   => ['label' => 'Attendance management',               'plan' => 'attendance',         'routes' => ['attendance-manager.']],
            'shifts'             => ['label' => 'Shift management',                     'plan' => 'shifts',             'routes' => ['shifts.']],
            'calendar_admin'     => ['label' => 'Calendar & events management',        'plan' => 'calendar_events',    'routes' => []],
        ],
        'Forms & documents' => [
            'forms_admin'        => ['label' => 'Build & assign forms',                'plan' => 'forms',              'routes' => ['company-forms.']],
            'form_responses'     => ['label' => 'Review form responses (inbox)',       'plan' => 'forms',              'routes' => ['company-forms.inbox', 'company-forms.responses', 'company-forms.submission']],
            'company_documents'  => ['label' => 'Company documents (e-signature)',     'plan' => 'esign',              'routes' => ['company-documents.']],
            'hr_documents'       => ['label' => 'HR documents',                        'plan' => 'hr_documents',       'routes' => ['hr-documents.']],
            'policies_admin'     => ['label' => 'Company policies',                    'plan' => 'policies',           'routes' => ['company-policies.']],
            'document_library'   => ['label' => 'Document library management',         'plan' => 'documents',          'routes' => []],
        ],
        'Communication' => [
            'announcements_admin' => ['label' => 'Manage announcements',               'plan' => 'announcements',      'routes' => []],
            'feedback_admin'      => ['label' => 'Feedback & suggestions (admin)',     'plan' => 'feedback',           'routes' => []],
        ],
        'Assets & tools' => [
            'equipment_admin'    => ['label' => 'Equipment management',                'plan' => 'equipment',          'routes' => ['equipment.']],
            'code_requests'      => ['label' => 'Code requests (send codes)',          'plan' => 'code_requests',      'routes' => ['code-requests.']],
            'sheets'             => ['label' => 'Sheets',                              'plan' => 'sheets',             'routes' => ['sheets.']],
        ],
        'Reports' => [
            'reports'            => ['label' => 'Reports',                             'plan' => 'reports',            'routes' => ['reports.', 'attendance-reports.']],
            'report_generator'   => ['label' => 'Report generator',                   'plan' => 'report_generator',   'routes' => ['reports.generate', 'reports.history']],
        ],
    ];

    /** All feature metadata, flattened: key => [label, group, plan, routes]. */
    public static function all(): array
    {
        $out = [];
        foreach (self::GROUPS as $group => $features) {
            foreach ($features as $key => $meta) {
                $out[$key] = $meta + ['group' => $group];
            }
        }
        return $out;
    }

    /** Grouped for UI: group label => [ key => label ]. */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::GROUPS as $group => $features) {
            foreach ($features as $key => $meta) {
                $out[$group][$key] = $meta['label'];
            }
        }
        return $out;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** The plan feature that must also be enabled, or null if always available. */
    public static function planFeature(string $key): ?string
    {
        return self::all()[$key]['plan'] ?? null;
    }

    /** Keep only real, known feature keys from arbitrary input. */
    public static function sanitize(array $keys): array
    {
        return array_values(array_intersect(array_map('strval', $keys), self::keys()));
    }

    /** The feature key(s) a given route name falls under (longest prefix wins). */
    public static function featureForRoute(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }
        $best = null;
        $bestLen = -1;
        foreach (self::all() as $key => $meta) {
            foreach (($meta['routes'] ?? []) as $prefix) {
                if (str_starts_with($routeName, $prefix) && strlen($prefix) > $bestLen) {
                    $best = $key;
                    $bestLen = strlen($prefix);
                }
            }
        }
        return $best;
    }
}
