<?php

namespace App\Http\Middleware;

use App\Services\TimezoneService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the authenticated user's effective display timezone available to the
 * app and views, WITHOUT changing PHP's global timezone or Laravel's canonical
 * app.timezone. Timestamps continue to be stored in the canonical timezone;
 * only the display layer converts to the user's timezone.
 */
class SetUserTimezone
{
    public function __construct(private TimezoneService $timezones)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $tz = $this->timezones->getEffectiveTimezone($user);

            // Display timezone only — does NOT touch config('app.timezone'),
            // so Eloquent keeps reading/writing in the canonical timezone.
            config(['app.timezone_display' => $tz]);
            View::share('userTimezone', $tz);
            View::share('userTimezoneAbbr', $this->timezones->abbreviation($user));
        }

        return $next($request);
    }
}
