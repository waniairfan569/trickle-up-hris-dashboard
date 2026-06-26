<?php

namespace App\Console\Commands;

use App\Models\ZktecoDevice;
use App\Services\ZktecoK50Service;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Relay agent for pull-only devices (e.g. the K50, which has no Cloud Server /
 * ADMS push). RUN THIS ON A PC ON THE OFFICE LAN — it pulls punches from the
 * local device and POSTs them to the live site's /iclock receiver, so a
 * datacenter server (which can't reach the device's private IP) still gets
 * the data.
 *
 *   php artisan zkteco:relay --device=1 --url=https://hour.trickleup.co
 */
class RelayZktecoToLive extends Command
{
    protected $signature = 'zkteco:relay
        {--device= : Device id or IP from the local DB (optional)}
        {--ip= : Device IP — use this to relay without a DB record}
        {--port=4370 : Device TCP port (used with --ip)}
        {--url= : Live base URL (default: ZKTECO_RELAY_URL or https://hour.trickleup.co)}
        {--sn= : Serial to register the device under on live (default: device serial or K50-<ip>)}
        {--tz= : Device timezone (used with --ip, default Asia/Karachi)}
        {--all : Relay all logs (ignore the saved cursor)}
        {--dry : Connect + count, but do not POST}';

    protected $description = 'Pull punches from a local ZKTeco device and forward them to the live /iclock receiver.';

    public function handle(ZktecoK50Service $service): int
    {
        $device = $this->resolveDevice();
        if (!$device) {
            $this->error('No device found. Pass --device=<id|ip>.');
            return self::FAILURE;
        }

        $url = rtrim($this->option('url') ?: env('ZKTECO_RELAY_URL', 'https://hour.trickleup.co'), '/');
        $sn = $this->option('sn') ?: ($device->serial_number ?: 'K50-' . str_replace('.', '-', $device->ip_address));
        $cursorKey = 'zkteco_relay_cursor_' . ($device->id ?: str_replace('.', '_', $device->ip_address));
        $cursor = $this->option('all') ? null : Cache::get($cursorKey);

        $this->info("Relaying {$device->name} ({$device->ip_address}:{$device->port}) -> {$url}  (SN={$sn})");

        // 1) Pull from the local device.
        try {
            $zk = $service->connect($device);
            $zk->disableDevice();
            $logs = $zk->getAttendance();
            $zk->enableDevice();
            $zk->disconnect();
        } catch (\Throwable $e) {
            $this->error('Pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 2) Build ATTLOG lines (PIN=uid so live maps by the same id as local pull).
        $lines = [];
        $maxTs = $cursor ? Carbon::parse($cursor) : null;
        foreach ($logs as $log) {
            try {
                $ts = Carbon::parse($log['timestamp']);
            } catch (\Throwable $e) {
                continue;
            }
            if ($cursor && $ts->lte(Carbon::parse($cursor))) {
                continue; // already relayed
            }
            $lines[] = implode("\t", [
                $log['uid'],
                $ts->format('Y-m-d H:i:s'),
                $log['state'] ?? 0,
                $log['type'] ?? 1,
            ]);
            if (!$maxTs || $ts->gt($maxTs)) {
                $maxTs = $ts;
            }
        }

        if (empty($lines)) {
            $this->info('Nothing new to relay.');
            return self::SUCCESS;
        }

        $this->info(count($lines) . ' new punch(es) to relay.');

        if ($this->option('dry')) {
            $this->line('[dry-run] ' . $lines[0] . (count($lines) > 1 ? '  …' : ''));
            return self::SUCCESS;
        }

        // 3) POST to the live /iclock receiver in batches.
        $endpoint = "{$url}/iclock/cdata?SN=" . rawurlencode($sn) . '&table=ATTLOG';
        $sent = 0;
        foreach (array_chunk($lines, 200) as $batch) {
            try {
                $res = Http::timeout(30)
                    ->withBody(implode("\n", $batch) . "\n", 'text/plain')
                    ->post($endpoint);
                if (!$res->successful()) {
                    $this->error("Live rejected a batch (HTTP {$res->status()}): " . $res->body());
                    return self::FAILURE;
                }
                $sent += count($batch);
            } catch (\Throwable $e) {
                $this->error('POST to live failed: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        if ($maxTs) {
            Cache::forever($cursorKey, $maxTs->toDateTimeString());
        }

        $this->info("Relayed {$sent} punch(es) to live.");

        return self::SUCCESS;
    }

    private function resolveDevice(): ?ZktecoDevice
    {
        // Standalone mode (no DB record needed) — just point at the K50's IP.
        if ($this->option('ip')) {
            return new ZktecoDevice([
                'name' => 'K50',
                'ip_address' => $this->option('ip'),
                'port' => (int) $this->option('port') ?: 4370,
                'timezone' => $this->option('tz') ?: 'Asia/Karachi',
            ]);
        }

        $opt = $this->option('device');
        if ($opt) {
            $found = is_numeric($opt)
                ? ZktecoDevice::find($opt)
                : ZktecoDevice::where('ip_address', $opt)->first();
            // If an IP was given but no DB row exists, relay it anyway.
            if (!$found && !is_numeric($opt)) {
                return new ZktecoDevice(['name' => 'K50', 'ip_address' => $opt, 'port' => (int) $this->option('port') ?: 4370, 'timezone' => 'Asia/Karachi']);
            }
            return $found;
        }

        return ZktecoDevice::where('is_active', true)
            ->where(fn ($q) => $q->where('connection_mode', '!=', 'push')->orWhereNull('connection_mode'))
            ->first();
    }
}
