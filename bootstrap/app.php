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
            'user.timezone'    => \App\Http\Middleware\SetUserTimezone::class,
            'operator'         => \App\Http\Middleware\EnsureOperator::class,
            'operator.owner'   => \App\Http\Middleware\EnsureOperatorOwner::class,
        ]);
        // Trust Plesk's reverse proxy so $request->secure() reflects the real
        // scheme (X-Forwarded-Proto) — required for ForceHttps to work without looping.
        $middleware->trustProxies(at: '*');

        // Force HTTPS (except /iclock) — runs first; controlled by config('app.force_https').
        $middleware->web(prepend: [
            \App\Http\Middleware\ForceHttps::class,
        ]);

        // Resolve the authenticated user's display timezone on every web request
        // (the middleware no-ops for guests).
        $middleware->web(append: [
            \App\Http\Middleware\SetCurrentTenant::class,
            \App\Http\Middleware\EnsureTrialActive::class,
            \App\Http\Middleware\EnforcePlanFeatures::class,
            \App\Http\Middleware\SetUserTimezone::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'iclock/*', // ZKTeco ADMS push endpoints — devices can't send CSRF tokens
            'stripe/webhook', // Stripe posts server-to-server; verified by signature
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Capture real errors (5xx / unexpected) for the operator error viewer.
        // ApplicationError::log filters out normal outcomes (404/403/419/validation)
        // and never throws, so this is safe on every request.
        $exceptions->report(function (\Throwable $e) {
            \App\Models\ApplicationError::log($e, request());
        });

        // Return JSON 401 for unauthenticated API requests instead of redirecting to 'login' route
        $exceptions->shouldRenderJsonWhen(fn($request, $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
