<?php

use App\Models\User;
use App\Services\TimezoneService;
use Carbon\Carbon;

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
