<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            if ($tenant) {
                // Always activate scoping so the user only ever sees their own
                // tenant's data (never leave it unset, which would be a leak).
                $this->tenants->set($tenant);

                // A suspended workspace is locked out (operators excepted).
                if ($tenant->status === 'suspended' && !$user->isOperator()) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'email' => 'This workspace has been suspended. Please contact support.',
                    ]);
                }
            }
        }

        return $next($request);
    }
}
