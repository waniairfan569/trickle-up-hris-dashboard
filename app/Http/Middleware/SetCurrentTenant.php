<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the tenant for the request from the authenticated user and activates
 * scoping. Runs after auth, so the login lookup itself is unscoped.
 */
class SetCurrentTenant
{
    public function __construct(private TenantManager $tenants)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && $tenant->status !== 'suspended') {
                $this->tenants->set($tenant);
            }
        }

        return $next($request);
    }
}
