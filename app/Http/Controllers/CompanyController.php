<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use App\Models\CompanyEntity;
use App\Models\CompanyForm;
use App\Models\CompanyPolicy;
use App\Models\Department;
use App\Models\FormSubmission;
use App\Models\OfficeLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\ZktecoDevice;
use App\Models\ZktecoUnmapped;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Company hub — one landing page with a tile per company-settings area so the
 * sidebar carries a single "Company" link instead of a nested dropdown. The
 * "General" tile opens Company Entities, which shares a tab strip with Workspace
 * Branding, Office Locations and Departments (see partials.company-general-tabs);
 * Security and Devices open their own sub-hubs.
 */
class CompanyController extends Controller
{
    public function index(Request $request, TenantManager $tenants)
    {
        $tiles = [
            [
                'route' => 'company-entities.index',
                'icon' => 'building-2',
                'tone' => 'brand',
                'title' => 'General',
                'text' => 'Company entities, workspace branding, office locations and departments.',
                'stat' => CompanyEntity::where('is_active', true)->count(),
                'stat_label' => 'entities',
                'meta' => Department::count() . ' departments · ' . OfficeLocation::where('is_active', true)->count() . ' locations',
            ],
        ];

        if (plan_allows('forms')) {
            // Same "awaiting" definition as the Form Responses inbox.
            $awaiting = FormSubmission::where('status', 'submitted')
                ->where(fn ($w) => $w->whereNull('review_status')->orWhere('review_status', 'pending'))
                ->count();

            $tiles[] = [
                'route' => 'company-forms.index',
                'icon' => 'clipboard-list',
                'tone' => 'sky',
                'title' => 'Company Forms',
                'text' => 'Build forms, assign them to people, and review responses in the inbox.',
                'stat' => CompanyForm::active()->count(),
                'stat_label' => 'active forms',
                'meta' => $awaiting . ' awaiting review',
                'badge' => $awaiting,
            ];
        }

        if (plan_allows('esign')) {
            $tiles[] = [
                'route' => 'company-documents.admin',
                'icon' => 'file-text',
                'tone' => 'violet',
                'title' => 'Company Documents',
                'text' => 'Shared files, categories and e-signature templates.',
                'stat' => CompanyDocument::count(),
                'stat_label' => 'documents',
                'meta' => null,
            ];
        }

        $tiles[] = [
            'route' => 'company-policies.index',
            'icon' => 'book-text',
            'tone' => 'emerald',
            'title' => 'Company Policies',
            'text' => 'Publish policies and track who has acknowledged them.',
            'stat' => CompanyPolicy::active()->count(),
            'stat_label' => 'active policies',
            'meta' => CompanyPolicy::active()->requiresAcknowledgment()->count() . ' need sign-off',
        ];

        $tenant = $this->resolveTenant($tenants);
        $plan = $tenant?->planModel();
        $tiles[] = [
            'route' => 'billing.index',
            'icon' => 'credit-card',
            'tone' => 'amber',
            'title' => 'Billing & Plans',
            'text' => 'Your subscription, seats, invoices and plan upgrades.',
            'stat' => $plan?->name ?? 'Trial',
            'stat_label' => 'current plan',
            'meta' => $tenant ? ucfirst((string) $tenant->status) : null,
        ];

        $tiles[] = [
            'route' => 'developer.api-tokens',
            'icon' => 'code-2',
            'tone' => 'indigo',
            'title' => 'API access',
            'text' => 'Personal access tokens for the REST API and integrations.',
            'stat' => $request->user()->tokens()->count(),
            'stat_label' => 'active tokens',
            'meta' => null,
        ];

        // Security and Devices are their own hubs (several pages each) — reached from here, not the sidebar.
        $tenantUserIds = User::pluck('id')->all();
        $tiles[] = [
            'route' => 'security.index',
            'icon' => 'shield-check',
            'tone' => 'rose',
            'title' => 'Security',
            'text' => 'Roles & permissions, who is signed in, and the audit trail of admin actions.',
            'stat' => DB::table('sessions')->whereIn('user_id', $tenantUserIds)->where('last_activity', '>=', now()->subMinutes(15)->timestamp)->count(),
            'stat_label' => 'signed in now',
            'meta' => Role::count() . ' roles',
        ];

        $unmapped = ZktecoUnmapped::unresolved()->count();
        $tiles[] = [
            'route' => 'devices.index',
            'icon' => 'hard-drive',
            'tone' => 'sky',
            'title' => 'Devices',
            'text' => 'Biometric clock-in terminals and how they sync with attendance.',
            'stat' => ZktecoDevice::where('is_active', true)->count(),
            'stat_label' => 'active devices',
            'meta' => $unmapped ? $unmapped . ' unmapped users' : null,
            'badge' => $unmapped,
        ];
        return view('company.index', compact('tiles'));
    }
}
