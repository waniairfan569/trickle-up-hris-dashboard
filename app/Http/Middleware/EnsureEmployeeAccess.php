<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class EnsureEmployeeAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentUser = $request->user();

        if (!$currentUser) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return \Illuminate\Support\Facades\Route::has('login')
                ? redirect()->route('login')->with('error', 'Please log in to access this page.')
                : redirect('/login')->with('error', 'Please log in to access this page.');
        }

        // 1. Resolve the target user from the route parameters
        $target = $request->route('user') ?? $request->route('employee') ?? $request->route('id');

        if ($target && !($target instanceof User)) {
            $target = User::find($target);
        }

        // If no target user is resolved, let the request proceed to let the controller handle it (or 404)
        if (!$target) {
            return $next($request);
        }

        // Eager load roles to avoid N+1
        $currentUser->loadMissing('roles');

        // 2. Perform access checks
        // Rule A: super_admin and hr_admin always pass
        if ($currentUser->isAdmin()) {
            return $next($request);
        }

        // Rule B: Anyone can access themselves
        if ($currentUser->id === $target->id) {
            return $next($request);
        }

        // Rule C: Managers can access their direct/indirect reporting line
        if ($currentUser->isManager()) {
            if ($currentUser->canManage($target)) {
                return $next($request);
            }
        }

        // Rule D: Default fallback: Employee/Restricted users cannot access other users
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Forbidden: You do not have access to this employee profile.'], 403);
        }

        return \Illuminate\Support\Facades\Route::has('dashboard')
            ? redirect()->route('dashboard')->with('error', 'You do not have permission to access that employee profile.')
            : redirect('/dashboard')->with('error', 'You do not have permission to access that employee profile.');
    }
}
