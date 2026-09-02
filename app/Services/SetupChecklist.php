<?php

namespace App\Services;

use App\Models\CompanyPolicy;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OfficeLocation;
use App\Models\Tenant;

/**
 * The "Getting started" setup checklist for a new workspace. Each step's done
 * state is derived from real workspace data, so it ticks off automatically as
 * the admin does the work — no manual marking. Drives the dashboard widget and
 * the Getting Started page.
 */
class SetupChecklist
{
    /** All steps with live done-state for the given workspace. */
    public function steps(Tenant $tenant): array
    {
        $count = fn (string $class) => $class::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

        $branded = $tenant->logo_url
            || ($tenant->primary_color && $tenant->primary_color !== '')
            || ($tenant->brand_name && $tenant->brand_name !== $tenant->name);

        return [
            [
                'key' => 'team',
                'title' => 'Invite your team',
                'description' => 'Add employees so they can clock in and use the app.',
                'icon' => 'user-plus',
                'route' => 'employees.pending-invitations',
                'cta' => 'Invite people',
                'done' => Employee::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_system', false)->exists(),
            ],
            [
                'key' => 'departments',
                'title' => 'Create departments',
                'description' => 'Organise people into teams and departments.',
                'icon' => 'network',
                'route' => 'departments.index',
                'cta' => 'Add a department',
                'done' => $count(Department::class) > 0,
            ],
            [
                'key' => 'location',
                'title' => 'Add an office location',
                'description' => 'Set where your team works — used for attendance and reports.',
                'icon' => 'map-pin',
                'route' => 'office-locations.index',
                'cta' => 'Add a location',
                'done' => $count(OfficeLocation::class) > 0,
            ],
            [
                'key' => 'policies',
                'title' => 'Publish company policies',
                'description' => 'Share handbooks and policies for staff to acknowledge.',
                'icon' => 'book-text',
                'route' => 'company-policies.index',
                'cta' => 'Add a policy',
                'done' => $count(CompanyPolicy::class) > 0,
            ],
            [
                'key' => 'branding',
                'title' => 'Brand your workspace',
                'description' => 'Add your logo and colour so the app feels like yours.',
                'icon' => 'palette',
                'route' => 'workspace.branding',
                'cta' => 'Customise branding',
                'done' => (bool) $branded,
            ],
        ];
    }

    /** {done, total, percent, complete} for the workspace. */
    public function progress(Tenant $tenant): array
    {
        $steps = $this->steps($tenant);
        $total = count($steps);
        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total ? (int) round($done / $total * 100) : 100,
            'complete' => $done === $total,
        ];
    }

    /** Show the checklist widget? (Not complete, and not dismissed.) */
    public function shouldShow(Tenant $tenant): bool
    {
        return !$tenant->onboarding_dismissed_at && !$this->progress($tenant)['complete'];
    }
}
