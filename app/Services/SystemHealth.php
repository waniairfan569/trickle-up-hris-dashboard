<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single source of truth for system health probes — used by the /health JSON
 * endpoint (uptime monitors) and the public /status page. Exposes no sensitive
 * data beyond per-subsystem up/down and timings.
 */
class SystemHealth
{
    /** Scheduler writes this cache key every minute (see routes/console.php). */
    public const HEARTBEAT_KEY = 'health:scheduler_heartbeat';

    /** db / cache / storage are critical; scheduler / queue are informational. */
    public const CRITICAL = ['database', 'cache', 'storage'];

    public function checks(): array
    {
        return [
            'database' => $this->database(),
            'cache' => $this->cache(),
            'storage' => $this->storage(),
            'scheduler' => $this->scheduler(),
            'queue' => $this->queue(),
        ];
    }

    public function criticalDown(array $checks): bool
    {
        foreach (self::CRITICAL as $key) {
            if (($checks[$key]['status'] ?? null) === 'down') {
                return true;
            }
        }

        return false;
    }

    public function overall(array $checks): string
    {
        if ($this->criticalDown($checks)) {
            return 'down';
        }

        foreach ($checks as $c) {
            if (in_array($c['status'] ?? '', ['down', 'degraded'], true)) {
                return 'degraded';
            }
        }

        return 'ok';
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

    private function scheduler(): array
    {
        try {
            $last = Cache::get(self::HEARTBEAT_KEY);
            if (!$last) {
                return ['status' => 'unknown', 'detail' => 'no heartbeat yet'];
            }

            // diffInSeconds is signed in Carbon 3 — a past timestamp is negative.
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

    private function queue(): array
    {
        try {
            $failed = DB::table('failed_jobs')->count();
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;

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
