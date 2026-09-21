<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;

/**
 * Organization Profile — the company (tenant) as a first-class record.
 *
 * One page that gathers the organization's identity and locale, with an
 * at-a-glance summary of plan, seats, people and roles. The editable fields
 * here are the org's legal identity and locale only; branding, billing and
 * people keep their own dedicated pages (linked from here) so nothing is
 * duplicated and each stays the single source of truth.
 */
class OrganizationProfileController extends Controller
{
    public function edit(TenantManager $tenants)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404, 'No active workspace.');

        // People snapshot — the global tenant scope keeps these to this company.
        $memberCount = User::count();
        $roleCounts  = Role::withCount('users')->orderBy('id')->get();

        // Owners & admins — who can administer this workspace (managed in Roles & access).
        $admins = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->with(['roles' => fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin'])])
            ->orderBy('first_name')->orderBy('last_name')
            ->get();
        $adminCount = $admins->count();

        $plan = $tenant->planModel();

        return view('organization.profile', compact('tenant', 'memberCount', 'adminCount', 'roleCounts', 'admins', 'plan'));
    }

    public function update(Request $request, TenantManager $tenants)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'from_email'   => 'nullable|email|max:255',
            'timezone'     => 'required|timezone',
            'currency'     => 'required|string|size:3|alpha',
            'company_size' => 'nullable|in:1-10,11-50,51-200,201-500,500+',
            'industry'     => 'nullable|string|max:80',
            'country'      => 'nullable|string|max:80',
        ], [
            'timezone.timezone' => 'Choose a valid timezone from the list.',
            'currency.size'     => 'Use a 3-letter currency code (e.g. USD, PKR, GBP).',
        ]);

        $tenant->name         = $data['name'];
        $tenant->from_email   = $data['from_email'] ?: null;
        $tenant->timezone     = $data['timezone'];
        $tenant->currency     = strtoupper($data['currency']);
        $tenant->company_size = $data['company_size'] ?? null;
        $tenant->industry     = $data['industry'] ?: null;
        $tenant->country      = $data['country'] ?: null;
        $tenant->save();

        return redirect()->route('organization.edit')->with('success', 'Organization profile updated.');
    }
}
