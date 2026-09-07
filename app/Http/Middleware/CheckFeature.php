<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route by a grantable feature (App\Support\FeatureCatalog). Admins
 * always pass; everyone else needs the feature via a custom role or a direct
 * grant. Plan gating (EnforcePlanFeatures) still applies independently.
 *
 * Usage: ->middleware('feature:reports')  (any one of several: feature:a,b)
 */
class CheckFeature
{
    public function handle(Request $request, Closure $next, ...$features): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return \Illuminate\Support\Facades\Route::has('login')
                ? redirect()->route('login')
                : redirect('/login');
        }

        foreach ($features as $feature) {
            if ($user->canFeature($feature)) {
                return $next($request);
            }
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Forbidden: this feature is not enabled for your account.'], 403);
        }

        return \Illuminate\Support\Facades\Route::has('dashboard')
            ? redirect()->route('dashboard')->with('error', 'You do not have access to that feature.')
            : redirect('/dashboard')->with('error', 'You do not have access to that feature.');
    }
}
