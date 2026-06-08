<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'super_admin'      => \App\Http\Middleware\EnsureSuperAdmin::class,
            'check_permission' => \App\Http\Middleware\CheckPermission::class,
            'role'             => \App\Http\Middleware\CheckRole::class,
            'permission'       => \App\Http\Middleware\CheckPermission::class,
            'employee.access'  => \App\Http\Middleware\EnsureEmployeeAccess::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
        ]);
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON 401 for unauthenticated API requests instead of redirecting to 'login' route
        $exceptions->shouldRenderJsonWhen(fn($request, $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
