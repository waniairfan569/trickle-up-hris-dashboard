<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $roles Comma-separated list of roles allowed
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return \Illuminate\Support\Facades\Route::has('login')
                ? redirect()->route('login')->with('error', 'Please log in to access this page.')
                : redirect('/login')->with('error', 'Please log in to access this page.');
        }

        // Eager load roles to avoid N+1 query issues
        $user->loadMissing('roles');

        $roleArray = $roles;

        if (!$user->hasRole($roleArray)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Forbidden: You do not have the required role.'], 403);
            }
            return \Illuminate\Support\Facades\Route::has('dashboard')
                ? redirect()->route('dashboard')->with('error', 'You do not have permission to access that section.')
                : redirect('/dashboard')->with('error', 'You do not have permission to access that section.');
        }

        return $next($request);
    }
}
