<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TimezoneService;
use App\Tenancy\TenantManager;
use Carbon\Carbon;

if (!function_exists('plan_allows')) {
    /**
     * Does the current workspace's subscription plan include this feature?
     * Mirrors EnforcePlanFeatures' tenant resolution and fails OPEN (returns
     * true) when no tenant can be resolved — so nav on a single-tenant/pre-SaaS
     * install is never hidden. Use it to hide plan-gated nav items:
     *   @if (plan_allows('sheets')) ... @endif
     */
    function plan_allows(string $feature): bool
    {
        $tenant = app(TenantManager::class)->get();

        if (!$tenant) {
            $user = auth()->user();
            if ($user && $user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
            } elseif (Tenant::query()->count() === 1) {
                $tenant = Tenant::query()->first();
            }
        }

        return !$tenant || $tenant->hasFeature($feature);
    }
}

if (!function_exists('usertime')) {
    /**
     * Format a canonical-stored timestamp in the given (or current) user's
     * effective timezone. Returns "—" for null timestamps.
     *
     * Usage: {{ usertime($attendance->clock_in) }}
     */
    function usertime(?Carbon $utcTime, ?User $user = null): string
    {
        $user = $user ?? auth()->user();

        return app(TimezoneService::class)->formatForUser($utcTime, $user);
    }
}

if (!function_exists('userdate')) {
    /**
     * Format a canonical-stored timestamp as a date (optionally with time) in
     * the given (or current) user's effective timezone.
     */
    function userdate(?Carbon $utcTime, ?User $user = null, bool $withTime = false): string
    {
        $user = $user ?? auth()->user();

        return app(TimezoneService::class)->formatDateForUser($utcTime, $user, $withTime);
    }
}
