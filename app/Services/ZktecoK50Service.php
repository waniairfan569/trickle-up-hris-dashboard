<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\CompanyEntity;
use App\Models\User;
use App\Models\ZktecoDevice;
use App\Models\ZktecoRawPunch;
use App\Models\ZktecoUnmapped;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Rats\Zkteco\Lib\ZKTeco;

class ZktecoK50Service
{
    public function connect(ZktecoDevice $device): ZKTeco
    {
        // Fast pre-flight reachability check so an offline / wrong-network device
        // fails in a few seconds with a clear message instead of hanging until
        // PHP's max_execution_time (which surfaces as a 500 error).
        $this->assertReachable($device);

        $zk = new ZKTeco($device->ip_address, $device->port);
        $connected = $zk->connect();

        if (!$connected) {
            throw new Exception("Cannot connect to ZKTeco at {$device->ip_address}:{$device->port}. Check: 1) device powered on, 2) IP correct, 3) device and server on the same network.");
        }

        return $zk;
    }

    /** Quick TCP probe (3s) — the K50 listens on its TCP Comm port. */
    private function assertReachable(ZktecoDevice $device): void
    {
        $errno = 0;
        $errstr = '';
        $sock = @fsockopen($device->ip_address, (int) $device->port, $errno, $errstr, 3);
        if ($sock === false) {
            throw new Exception("Device unreachable at {$device->ip_address}:{$device->port} — make sure it is powered on and on the same network as this server (your PC's IP must start with the same numbers as the device's IP). [{$errstr}]");
        }
        fclose($sock);
    }

    public function testConnection(ZktecoDevice $device): array
    {
        try {
            $zk = $this->connect($device);
            $zk->disableDevice();
            $zk->enableDevice();
            $zk->disconnect();
            return ['success' => true, 'message' => 'Successfully connected to device.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function syncDevice(ZktecoDevice $device): array
    {
        try {
            $zk = $this->connect($device);
        } catch (Exception $e) {
            $device->update([
                'last_sync_status' => 'failed',
                'last_sync_message' => $e->getMessage()
            ]);
            throw $e;
        }

        try {
            $zk->disableDevice();
            $logs = $zk->getAttendance();
            $zk->enableDevice();
            $zk->disconnect();
            
            $synced = 0;
            $imported = 0;
            $duplicates = 0;
            $unmapped = 0;

            // ZKTeco devices report punches in their own configured local time.
            // Resolve that timezone (device override, else primary entity) and
            // convert every punch into the app's canonical timezone for storage.
            $deviceTz = $device->timezone
                ?: (optional(CompanyEntity::primary())->timezone ?: TimezoneService::FALLBACK_TIMEZONE);
            $canonicalTz = config('app.timezone') ?: TimezoneService::FALLBACK_TIMEZONE;

            foreach ($logs as $log) {
                $punchedAt = Carbon::parse($log['timestamp'], $deviceTz)->setTimezone($canonicalTz);

                if ($device->last_synced_at && $punchedAt->lte($device->last_synced_at)) {
                    continue;
                }

                $exists = ZktecoRawPunch::where([
                    'device_id' => $device->id,
                    'zkteco_uid' => $log['uid'],
                    'punched_at' => $punchedAt
                ])->exists();

                if ($exists) {
                    $duplicates++;
                    continue;
                }

                $user = User::where('zkteco_uid', $log['uid'])->first();

                try {
                    $rawPunch = ZktecoRawPunch::create([
                        'device_id' => $device->id,
                        'zkteco_uid' => $log['uid'],
                        'zkteco_employee_id' => $log['id'],
                        'user_id' => $user?->id,
                        'punched_at' => $punchedAt,
                        'punch_state' => $log['state'],
                        'verify_type' => $log['type'],
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Duplicate punch (unique device+uid+time) — skip, don't abort the sync.
                    if (($e->getCode() === '23000') || str_contains($e->getMessage(), 'Duplicate entry')) {
                        $duplicates++;
                        continue;
                    }
                    throw $e;
                }

                $synced++;

                if (!$user) {
                    $unmappedRecord = ZktecoUnmapped::firstOrNew([
                        'device_id' => $device->id,
                        'zkteco_uid' => $log['uid']
                    ]);
                    
                    if (!$unmappedRecord->exists) {
                        $unmappedRecord->first_seen = $punchedAt;
                        $unmappedRecord->punch_count = 0;
                    }
                    
                    $unmappedRecord->zkteco_employee_id = $log['id'];
                    $unmappedRecord->punch_count++;
                    $unmappedRecord->last_seen = $punchedAt;
                    $unmappedRecord->save();

                    $unmapped++;
                    continue;
                }

                $this->processPunch($rawPunch, $user);
                $imported++;
            }

            $device->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => null,
                'total_records_synced' => DB::raw('total_records_synced + ' . $synced)
            ]);

            return [
                'synced' => $synced,
                'imported' => $imported,
                'duplicates' => $duplicates,
                'unmapped' => $unmapped
            ];
            
        } catch (Exception $e) {
            $device->update([
                'last_sync_status' => 'failed',
                'last_sync_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Ingest a single punch (used by ADMS push mode). Dedupes, maps to a user
     * or records it as unmapped, and runs it through the attendance pipeline.
     * $punchedAt must already be in the canonical (app) timezone.
     *
     * @return string one of: imported, unmapped, duplicate
     */
    public function ingestPunch(ZktecoDevice $device, $uid, $employeeId, Carbon $punchedAt, int $state, int $verify): string
    {
        if (ZktecoRawPunch::where([
            'device_id' => $device->id,
            'zkteco_uid' => $uid,
            'punched_at' => $punchedAt,
        ])->exists()) {
            return 'duplicate';
        }

        $user = User::where('zkteco_uid', $uid)->first();

        try {
            $punch = ZktecoRawPunch::create([
                'device_id' => $device->id,
                'zkteco_uid' => $uid,
                'zkteco_employee_id' => $employeeId,
                'user_id' => $user?->id,
                'punched_at' => $punchedAt,
                'punch_state' => $state,
                'verify_type' => $verify,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->getCode() === '23000') || str_contains($e->getMessage(), 'Duplicate entry')) {
                return 'duplicate';
            }
            throw $e;
        }

        if (!$user) {
            $unmapped = ZktecoUnmapped::firstOrNew([
                'device_id' => $device->id,
                'zkteco_uid' => $uid,
            ]);
            if (!$unmapped->exists) {
                $unmapped->first_seen = $punchedAt;
                $unmapped->punch_count = 0;
            }
            $unmapped->zkteco_employee_id = $employeeId;
            $unmapped->punch_count++;
            $unmapped->last_seen = $punchedAt;
            $unmapped->save();

            return 'unmapped';
        }

        $this->processPunch($punch, $user);

        return 'imported';
    }

    public function processPunch(ZktecoRawPunch $punch, User $user, string $sourceOverride = null): void
    {
        $punchType = $punch->punch_type_label;

        // Only Check-In / Check-Out drive attendance. Ignore Break / Overtime
        // punches entirely (don't even create a record), but mark them processed.
        if (!in_array($punchType, ['clock_in', 'clock_out'], true)) {
            $punch->update(['is_processed' => true, 'processed_at' => now(), 'user_id' => $user->id]);
            return;
        }

        $tz = app(TimezoneService::class);
        $shiftService = app(ShiftService::class);

        // Decisions about which day a punch belongs to, and late/overtime, are
        // made in the employee's LOCAL timezone. Stored clock_in/clock_out stay
        // in the canonical timezone (the display layer converts them back).
        $userTz = $tz->getEffectiveTimezone($user);
        $localPunch = $tz->toUserTime($punch->punched_at, $user);
        $date = $localPunch->toDateString();
        $source = $sourceOverride ?? 'zkteco';

        $record = AttendanceRecord::firstOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            ['status' => 'absent', 'source' => $source]
        );
        $shift = $shiftService->getShiftForUserOnDate($user, $localPunch->copy());

        if ($punchType === 'clock_in') {
            if (!$record->clock_in || $punch->punched_at->lessThan($record->clock_in)) {
                $record->clock_in = $punch->punched_at;
                $record->source = $source;
                $record->zkteco_punch_id = $punch->id;

                if ($shift && $shift->start_time) {
                    $gracePeriod = 15;
                    // Shift start_time is wall-clock local to the employee.
                    $expectedStart = Carbon::parse($date . ' ' . $shift->start_time, $userTz);
                    $lateMinutes = $localPunch->greaterThan($expectedStart->copy()->addMinutes($gracePeriod))
                        ? $localPunch->diffInMinutes($expectedStart)
                        : 0;
                    $record->late_minutes = $lateMinutes;
                    $record->status = $lateMinutes > 0 ? 'late' : 'present';
                } else {
                    $record->status = 'present';
                }
                $record->save();
            }
        }

        if ($punchType === 'clock_out') {
            if (!$record->clock_out || $punch->punched_at->greaterThan($record->clock_out)) {
                $record->clock_out = $punch->punched_at;
                if ($record->clock_in) {
                    $breakMinutes = $record->breaks()->whereNotNull('break_end')->sum('duration_minutes') ?? 0;
                    $record->total_minutes_worked = max(0, $punch->punched_at->diffInMinutes($record->clock_in) - $breakMinutes);

                    if ($shift && $shift->end_time) {
                        $overtimeGrace = 30;
                        $expectedEnd = Carbon::parse($date . ' ' . $shift->end_time, $userTz);
                        if ($shift->crosses_midnight) {
                            $expectedEnd->addDay();
                        }
                        $record->overtime_minutes = $localPunch->greaterThan($expectedEnd->copy()->addMinutes($overtimeGrace))
                            ? $localPunch->diffInMinutes($expectedEnd) : 0;
                        if ($record->overtime_minutes > 0 && $record->status === 'present') $record->status = 'overtime';
                        if ($record->overtime_minutes > 0 && $record->status === 'late') $record->status = 'late';
                    }
                }
                $record->save();
            }
        }

        $punch->update([
            'is_processed' => true,
            'processed_at' => now(),
            'user_id' => $user->id
        ]);
    }

    public function resolveMapping(int $zktecoUid, User $employee, User $resolvedBy): void
    {
        $unmapped = ZktecoUnmapped::where('zkteco_uid', $zktecoUid)->first();
        $employeeIdOnDevice = $unmapped ? $unmapped->zkteco_employee_id : (string)$zktecoUid;

        $employee->update([
            'zkteco_uid' => $zktecoUid,
            'zkteco_employee_id' => $employeeIdOnDevice
        ]);

        if ($unmapped) {
            $unmapped->update([
                'is_resolved' => true,
                'resolved_user_id' => $employee->id,
                'resolved_at' => now()
            ]);
        }

        $unprocessedPunches = ZktecoRawPunch::where('zkteco_uid', $zktecoUid)
            ->where('is_processed', false)
            ->get();

        foreach ($unprocessedPunches as $punch) {
            $this->processPunch($punch, $employee);
        }
    }
}
