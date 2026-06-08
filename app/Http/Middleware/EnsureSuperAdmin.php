<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Load role relation if not already loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        $roleName = optional($user->role)->name ?? '';
        if (strtolower($roleName) !== 'super admin') {
            return response()->json(['message' => 'Forbidden Access'], 403);
        }

        return $next($request);
    }
}
