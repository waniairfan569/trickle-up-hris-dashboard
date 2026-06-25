<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntity;
use App\Models\ZktecoDevice;
use App\Services\TimezoneService;
use App\Services\ZktecoK50Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco ADMS / "push" protocol receiver.
 *
 * Devices configured with Cloud Server Settings POST their punches to this
 * server over the internet, so it works even when the server can't dial the
 * device's private LAN IP. Endpoints (no auth, CSRF-excepted):
 *   GET  /iclock/cdata?SN=...&options=all   -> handshake (returns options)
 *   POST /iclock/cdata?SN=...&table=ATTLOG   -> attendance upload
 *   GET  /iclock/getrequest?SN=...           -> command poll (we return OK)
 */
class ZktecoPushController extends Controller
{
    public function __construct(private ZktecoK50Service $service)
    {
    }

    /** Handshake (GET) + data upload (POST). */
    public function cdata(Request $request)
    {
        $sn = (string) $request->query('SN', $request->query('sn', ''));
        if ($sn === '') {
            return $this->text('OK');
        }

        $device = $this->resolveDevice($sn, $request->ip());

        // GET = handshake: tell the device how/what to push.
        if ($request->isMethod('get')) {
            return $this->text(implode("\n", [
                'GET OPTION FROM: ' . $sn,
                'Stamp=9999',
                'OpStamp=9999',
                'ErrorDelay=30',
                'Delay=10',
                'TransTimes=00:00;23:59',
                'TransInterval=1',
                'TransFlag=1111111111',
                'Realtime=1',
                'Encrypt=0',
            ]) . "\n");
        }

        // POST = data upload. Only ATTLOG (attendance) is processed; other
        // tables (OPERLOG, etc.) are acknowledged and ignored.
        $table = strtoupper((string) $request->query('table', ''));
        $body = $request->getContent();

        if ($table !== 'ATTLOG' || trim($body) === '') {
            return $this->text('OK');
        }

        $deviceTz = $device->timezone
            ?: (optional(CompanyEntity::primary())->timezone ?: TimezoneService::FALLBACK_TIMEZONE);
        $canonicalTz = config('app.timezone') ?: TimezoneService::FALLBACK_TIMEZONE;

        $imported = 0;
        $unmapped = 0;
        $duplicates = 0;

        foreach (preg_split('/\r\n|\r|\n/', trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // ATTLOG line: PIN \t datetime \t status \t verify \t workcode \t ...
            $parts = preg_split('/\t/', $line);
            if (count($parts) < 2) {
                continue;
            }

            $pin = trim($parts[0]);
            $timeStr = trim($parts[1]);
            $state = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : 0;
            $verify = isset($parts[3]) && $parts[3] !== '' ? (int) $parts[3] : 1;

            try {
                $punchedAt = Carbon::parse($timeStr, $deviceTz)->setTimezone($canonicalTz);
            } catch (\Throwable $e) {
                continue; // skip unparseable timestamp
            }

            try {
                $result = $this->service->ingestPunch($device, $pin, $pin, $punchedAt, $state, $verify);
            } catch (\Throwable $e) {
                Log::warning('ZKTeco push ingest failed: ' . $e->getMessage());
                continue;
            }

            match ($result) {
                'imported' => $imported++,
                'unmapped' => $unmapped++,
                default => $duplicates++,
            };
        }

        $device->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => "Push: {$imported} imported, {$unmapped} unmapped, {$duplicates} duplicate.",
            'total_records_synced' => $device->total_records_synced + $imported + $unmapped,
        ]);

        return $this->text('OK: ' . ($imported + $unmapped));
    }

    /** The device polls for commands; we have none, so acknowledge. */
    public function getrequest(Request $request)
    {
        $sn = (string) $request->query('SN', $request->query('sn', ''));
        if ($sn !== '') {
            $this->resolveDevice($sn, $request->ip());
        }

        return $this->text('OK');
    }

    /** Command result callback — acknowledge. */
    public function devicecmd(Request $request)
    {
        return $this->text('OK');
    }

    /** Some firmwares probe /iclock/ping first. */
    public function ping(Request $request)
    {
        return $this->text('OK');
    }

    // ------------------------------------------------------------------

    /** Find the device by serial number, auto-registering on first contact. */
    private function resolveDevice(string $sn, ?string $ip): ZktecoDevice
    {
        return ZktecoDevice::firstOrCreate(
            ['serial_number' => $sn],
            [
                'name' => 'ZKTeco ' . $sn,
                'connection_mode' => 'push',
                'ip_address' => $ip ?: '0.0.0.0',
                'port' => 4370,
                'is_active' => true,
                'last_sync_status' => 'never',
            ]
        );
    }

    private function text(string $body)
    {
        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
