<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
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

        // Eager load roles and their permissions to avoid N+1
        $user->loadMissing('roles.permissions');

        if (!$user->hasPermission($permission)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Forbidden: You do not have the required permission: ' . $permission], 403);
            }
            return \Illuminate\Support\Facades\Route::has('dashboard')
                ? redirect()->route('dashboard')->with('error', 'You do not have permission to perform this action.')
                : redirect('/dashboard')->with('error', 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
