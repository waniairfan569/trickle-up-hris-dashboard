<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\User;
use App\Models\ZktecoRawPunch;
use App\Models\ZktecoUnmapped;
use Carbon\Carbon;
use App\Services\ZktecoK50Service;

class ZktecoExcelImport implements ToCollection, WithHeadingRow
{
    public $result = [
        'imported' => 0,
        'skipped' => 0,
        'unmapped' => 0,
        'total' => 0,
    ];

    public function collection(Collection $rows)
    {
        $zktecoService = app(ZktecoK50Service::class);
        $this->result['total'] = $rows->count();

        foreach ($rows as $row) {
            $rowArray = $row->toArray();
            
            // Extract employee ID (check common variations)
            $uid = null;
            $possibleUidKeys = ['no', 'id', 'employee_id', 'emp_id', 'userid', 'ac_no', 'enno', 'emp_no', 'user_id', 'pin'];
            foreach ($possibleUidKeys as $key) {
                if (isset($rowArray[$key])) {
                    $uid = $rowArray[$key];
                    break;
                }
            }
            if (!$uid) continue;

            // Extract datetime
            $datetimeStr = null;
            $possibleTimeKeys = ['date_time', 'time', 'check_time', 'datetime', 'clock_in_time', 'punch_time'];
            foreach ($possibleTimeKeys as $key) {
                if (isset($rowArray[$key])) {
                    $datetimeStr = $rowArray[$key];
                    break;
                }
            }
            
            if (!$datetimeStr && isset($rowArray['date']) && isset($rowArray['time'])) {
                // Try combining date and time columns if separate
                if (is_numeric($rowArray['date'])) {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rowArray['date'])->format('Y-m-d');
                } else {
                    $date = $rowArray['date'];
                }
                
                if (is_numeric($rowArray['time'])) {
                    $time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rowArray['time'])->format('H:i:s');
                } else {
                    $time = $rowArray['time'];
                }
                $datetimeStr = $date . ' ' . $time;
            }

            if (!$datetimeStr) continue;

            try {
                if (is_numeric($datetimeStr)) {
                    $punchedAt = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($datetimeStr));
                } else {
                    $punchedAt = Carbon::parse($datetimeStr);
                }
            } catch (\Exception $e) {
                continue;
            }

            // Extract punch type
            $punchStateRaw = $rowArray['punch_state'] ?? $rowArray['type'] ?? $rowArray['state'] ?? $rowArray['status'] ?? '0';
            $stateRawLower = strtolower((string)$punchStateRaw);
            $punchState = 0; // default check_in
            if (in_array($stateRawLower, ['1', 'check out', 'out'])) {
                $punchState = 1;
            }

            $user = User::where('zkteco_uid', $uid)->orWhere('zkteco_employee_id', $uid)->first();

            // Store as RawPunch
            // Excel import won't have a device_id necessarily, we might use a dummy one or the first one
            $device = \App\Models\ZktecoDevice::first();
            $deviceId = $device ? $device->id : null;

            if ($deviceId) {
                $exists = ZktecoRawPunch::where([
                    'device_id' => $deviceId,
                    'zkteco_uid' => $uid,
                    'punched_at' => $punchedAt
                ])->exists();

                if ($exists) {
                    $this->result['skipped']++;
                    continue;
                }
            }

            $rawPunch = new ZktecoRawPunch([
                'device_id' => $deviceId,
                'zkteco_uid' => $uid,
                'zkteco_employee_id' => $uid,
                'user_id' => $user?->id,
                'punched_at' => $punchedAt,
                'punch_state' => $punchState,
                'verify_type' => 1,
            ]);

            if ($deviceId) {
                $rawPunch->save();
            }

            if (!$user) {
                if ($deviceId) {
                    $unmappedRecord = ZktecoUnmapped::firstOrNew([
                        'device_id' => $deviceId,
                        'zkteco_uid' => $uid
                    ]);
                    
                    if (!$unmappedRecord->exists) {
                        $unmappedRecord->first_seen = $punchedAt;
                        $unmappedRecord->punch_count = 0;
                    }
                    
                    $unmappedRecord->zkteco_employee_id = $uid;
                    $unmappedRecord->punch_count++;
                    $unmappedRecord->last_seen = $punchedAt;
                    $unmappedRecord->save();
                }

                $this->result['unmapped']++;
                continue;
            }

            $zktecoService->processPunch($rawPunch, $user, 'excel_import');
            $this->result['imported']++;
        }
    }
}
