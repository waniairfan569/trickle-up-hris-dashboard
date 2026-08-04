<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Tenancy\TenantManager;

abstract class Controller
{
    /**
     * The workspace (tenant) for the current request. Prefers the one the
     * middleware activated, but falls back to the signed-in user's OWN tenant
     * (and, for a single-tenant install, the only tenant) so tenant-specific
     * pages never 404 just because the manager wasn't populated for this request.
     */
    protected function resolveTenant(TenantManager $tenants): ?Tenant
    {
        if ($current = $tenants->get()) {
            return $current;
        }

        $user = auth()->user();
        if ($user && $user->tenant_id) {
            return Tenant::find($user->tenant_id);
        }

        return Tenant::query()->count() === 1 ? Tenant::query()->first() : null;
    }
}
