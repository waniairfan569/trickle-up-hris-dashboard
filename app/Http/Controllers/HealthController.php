<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Public operational health endpoint for uptime monitors and load balancers.
 * GET /health → JSON with per-subsystem status. Returns 200 when everything
 * critical is up, 503 when a critical subsystem (db / cache / storage) is down.
 * Non-critical signals (scheduler heartbeat, queue backlog) mark the overall
 * status "degraded" but still return 200 so a monitor doesn't page for a
 * backlog. Exposes no sensitive data.
 */
class HealthController extends Controller
{
    /** Scheduler writes this cache key every minute (see routes/console.php). */
    public const HEARTBEAT_KEY = 'health:scheduler_heartbeat';

    public function __invoke()
    {
        $checks = [
            'database' => $this->database(),
            'cache' => $this->cache(),
            'storage' => $this->storage(),
            'scheduler' => $this->scheduler(),
            'queue' => $this->queue(),
        ];

        $criticalDown = collect($checks)
            ->only(['database', 'cache', 'storage'])
            ->contains(fn ($c) => $c['status'] === 'down');

        $anyDegraded = collect($checks)->contains(fn ($c) => in_array($c['status'], ['down', 'degraded'], true));

        $overall = $criticalDown ? 'down' : ($anyDegraded ? 'degraded' : 'ok');

        return response()->json([
            'status' => $overall,
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'version' => config('app.version', env('APP_VERSION', 'n/a')),
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $criticalDown ? 503 : 200);
    }

    private function database(): array
    {
        return $this->measure(function () {
            DB::connection()->select('SELECT 1');

            return ['status' => 'ok'];
        });
    }

    private function cache(): array
    {
        return $this->measure(function () {
            $key = 'health:probe:' . Str::random(8);
            Cache::put($key, '1', 10);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return ['status' => $ok ? 'ok' : 'down'];
        });
    }

    private function storage(): array
    {
        return $this->measure(function () {
            $disk = Storage::disk(config('filesystems.default'));
            $file = 'health/probe-' . Str::random(8) . '.txt';
            $disk->put($file, 'ok');
            $ok = $disk->get($file) === 'ok';
            $disk->delete($file);

            return ['status' => $ok ? 'ok' : 'down'];
        });
    }

    /** Confirms the scheduler cron is actually running (heartbeat freshness). */
    private function scheduler(): array
    {
        try {
            $last = Cache::get(self::HEARTBEAT_KEY);
            if (!$last) {
                return ['status' => 'unknown', 'detail' => 'no heartbeat yet'];
            }

            // diffInSeconds is signed in Carbon 3 — a past timestamp is negative,
            // so take the absolute age.
            $age = (int) abs(now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last)));

            return [
                'status' => $age <= 300 ? 'ok' : 'degraded',
                'last_run' => (string) $last,
                'age_seconds' => $age,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown'];
        }
    }

    /** Reports queue backlog / failures — informational, never critical. */
    private function queue(): array
    {
        try {
            $failed = DB::table('failed_jobs')->count();
            $pending = \Illuminate\Support\Facades\Schema::hasTable('jobs')
                ? DB::table('jobs')->count()
                : 0;

            return [
                'status' => $failed > 0 ? 'degraded' : 'ok',
                'pending' => $pending,
                'failed' => $failed,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown'];
        }
    }

    private function measure(callable $probe): array
    {
        $start = microtime(true);
        try {
            $result = $probe();
        } catch (\Throwable $e) {
            $result = ['status' => 'down', 'error' => class_basename($e)];
        }
        $result['ms'] = round((microtime(true) - $start) * 1000, 1);

        return $result;
    }
}
