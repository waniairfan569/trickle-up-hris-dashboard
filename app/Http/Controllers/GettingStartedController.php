<?php

namespace App\Http\Controllers;

use App\Services\SetupChecklist;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;

/** The new-workspace "Getting started" setup wizard/checklist. */
class GettingStartedController extends Controller
{
    public function show(TenantManager $tenants, SetupChecklist $checklist)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        return view('onboarding.getting-started', [
            'tenant' => $tenant,
            'steps' => $checklist->steps($tenant),
            'progress' => $checklist->progress($tenant),
        ]);
    }

    public function dismiss(Request $request, TenantManager $tenants)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        $tenant->forceFill(['onboarding_dismissed_at' => now()])->save();

        return redirect()->route('dashboard')->with('success', 'Setup checklist hidden. You can still find everything in the sidebar.');
    }
}
