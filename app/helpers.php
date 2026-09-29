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

if (!function_exists('hr_field_text')) {
    /**
     * A stored HR-document field value rendered as plain text.
     *
     * Field values are whatever the builder or the attendance prefill produced:
     * a string, a list of checkbox options, or a TABLE — a list of row arrays,
     * which is what the lateness / absence prefill writes. imploding a nested
     * array raises "Array to string conversion", and Laravel's error handler
     * turns that into a thrown ErrorException (a 500 on any page rendering the
     * document), so every renderer formats values through here.
     */
    function hr_field_text($value, string $glue = ', '): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        $parts = [];
        foreach ($value as $item) {
            $text = is_array($item) ? hr_field_text($item, ' · ') : hr_field_text($item);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode($glue, $parts);
    }
}
