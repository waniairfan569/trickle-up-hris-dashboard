<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $allowedRoutes = [
                'password.change',
                'password.update',
                'logout'
            ];

            if (!$request->routeIs($allowedRoutes)) {
                return redirect()->route('password.change')->with('warning', 'Please set a new password before continuing.');
            }
        }

        return $next($request);
    }
}
