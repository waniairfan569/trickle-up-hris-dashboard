<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Trial-expiry wall. Once a workspace's free trial has ended (status still
 * 'trialing' with trial_ends_at in the past — i.e. it never upgraded), the app
 * is limited: admins can still reach billing to choose a plan, everyone else
 * sees a "trial ended" page. Paying (billing.subscribe) flips the workspace to
 * 'active' and lifts the wall immediately.
 *
 * A workspace that stays expired past the grace period is hard-suspended by the
 * subscriptions:check-trials command, at which point SetCurrentTenant's suspend
 * lockout takes over. Operators are never walled. Fails OPEN when no tenant
 * resolves (single-tenant / pre-SaaS install).
 *
 * Runs before EnforcePlanFeatures so an expired trial is caught first.
 */
class EnsureTrialActive
{
    /** Reachable by anyone while the trial wall is up. */
    private const ALWAYS_ALLOWED = ['logout', 'login', 'password.', 'two-factor.', 'verification.'];

    /** Additionally reachable by admins, so they can pay and lift the wall. */
    private const ADMIN_ALLOWED = ['billing.'];

    public function __construct(private TenantManager $tenants)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->currentTenant();

        // No tenant, or the trial hasn't expired (active trial / paid / already
        // suspended-and-handled-elsewhere) — nothing to wall.
        if (!$tenant || !$tenant->trialExpired()) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && method_exists($user, 'isOperator') && $user->isOperator()) {
            return $next($request);
        }

        $name = $request->route()?->getName();
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();

        if ($name !== null && $this->isAllowed($name, $isAdmin)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(402, 'Your free trial has ended. Choose a plan to continue.');
        }

        return response()->view('billing.trial-ended', [
            'planName'  => $tenant->planConfig()['name'] ?? ucfirst((string) $tenant->plan),
            'canManage' => $isAdmin,
        ], 402);
    }

    private function isAllowed(string $name, bool $isAdmin): bool
    {
        $prefixes = self::ALWAYS_ALLOWED;
        if ($isAdmin) {
            $prefixes = array_merge($prefixes, self::ADMIN_ALLOWED);
        }

        foreach ($prefixes as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
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
