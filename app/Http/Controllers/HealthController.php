<?php

namespace App\Http\Controllers;

use App\Services\SystemHealth;

/**
 * Public operational health endpoint for uptime monitors and load balancers.
 * GET /health → JSON with per-subsystem status (via SystemHealth). Returns 200
 * when everything critical is up, 503 when a critical subsystem (db / cache /
 * storage) is down. Non-critical signals (scheduler heartbeat, queue backlog)
 * mark the overall status "degraded" but still return 200. No sensitive data.
 */
class HealthController extends Controller
{
    /** Back-compat alias — canonical key lives on SystemHealth. */
    public const HEARTBEAT_KEY = SystemHealth::HEARTBEAT_KEY;

    public function __invoke(SystemHealth $health)
    {
        $checks = $health->checks();
        $criticalDown = $health->criticalDown($checks);

        return response()->json([
            'status' => $health->overall($checks),
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'version' => config('app.version', env('APP_VERSION', 'n/a')),
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $criticalDown ? 503 : 200);
    }
}
