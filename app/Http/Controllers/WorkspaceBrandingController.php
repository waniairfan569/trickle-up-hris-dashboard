<?php

namespace App\Http\Controllers;

use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkspaceBrandingController extends Controller
{
    public function edit(TenantManager $tenants)
    {
        $tenant = $tenants->get();
        abort_unless($tenant, 404, 'No active workspace.');

        return view('workspace.branding', compact('tenant'));
    }

    public function update(Request $request, TenantManager $tenants)
    {
        $tenant = $tenants->get();
        abort_unless($tenant, 404);

        $data = $request->validate([
            'brand_name' => 'required|string|max:60',
            'primary_color' => 'nullable|regex:/^#([0-9a-fA-F]{6})$/',
            'logo' => 'nullable|image|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);

        $tenant->brand_name = $data['brand_name'];
        $tenant->primary_color = $data['primary_color'] ?: null;

        if ($request->boolean('remove_logo')) {
            $tenant->logo_url = null;
        } elseif ($request->hasFile('logo')) {
            $path = $request->file('logo')->store(\App\Tenancy\TenantStorage::path('tenant-logos'), 'public');
            $tenant->logo_url = Storage::url($path);
        }

        $tenant->save();

        return back()->with('success', 'Workspace branding updated.');
    }
}
