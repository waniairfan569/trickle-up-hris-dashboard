<?php

namespace App\Http\Controllers;

use App\Models\AttendanceImportLog;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use App\Models\ZktecoDevice;
use App\Models\ZktecoRawPunch;
use App\Models\ZktecoUnmapped;
use App\Services\ExcelImportService;
use App\Services\ZktecoK50Service;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZktecoController extends Controller
{
    public function dashboard()
    {
        $lastSync = ZktecoDevice::max('last_synced_at');
        $todayImported = \App\Models\ZktecoRawPunch::whereDate('punched_at', today())->count();
        $unmappedCount = ZktecoUnmapped::unresolved()->count();
        $totalDevices = ZktecoDevice::count();

        $devices = ZktecoDevice::all();

        return view('zkteco.dashboard', compact('lastSync', 'todayImported', 'unmappedCount', 'totalDevices', 'devices'));
    }

    public function devices()
    {
        $devices = ZktecoDevice::all();
        return view('zkteco.devices', compact('devices'));
    }

    public function storeDevice(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'required|integer',
            'serial_number' => 'nullable|string|max:100|unique:zkteco_devices,serial_number',
            'connection_mode' => 'nullable|in:pull,push',
        ]);
        $validated['connection_mode'] = $validated['connection_mode'] ?? 'pull';

        ZktecoDevice::create($validated);

        return back()->with('success', 'Device added successfully.');
    }

    public function updateDevice(Request $request, ZktecoDevice $device)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'required|integer',
            'serial_number' => 'nullable|string|max:100|unique:zkteco_devices,serial_number,' . $device->id,
            'connection_mode' => 'required|in:pull,push',
        ]);

        $device->update($validated);

        return back()->with('success', "“{$device->name}” updated.");
    }

    public function destroyDevice(ZktecoDevice $device)
    {
        $name = $device->name;
        $device->delete();

        return back()->with('success', "“{$name}” removed.");
    }

    public function testConnection(ZktecoDevice $device, ZktecoK50Service $service)
    {
        $result = $service->testConnection($device);
        return response()->json($result);
    }

    public function syncNow(ZktecoDevice $device, ZktecoK50Service $service)
    {
        try {
            $result = $service->syncDevice($device);
            return back()->with('success', "Synced: {$result['imported']} imported, {$result['unmapped']} unmapped, {$result['duplicates']} duplicates skipped.");
        } catch (Exception $e) {
            return back()->with('error', 'Failed to sync: ' . $e->getMessage());
        }
    }

    public function unmapped()
    {
        $unmapped = ZktecoUnmapped::unresolved()->with('device')->get();
        $employees = User::whereNull('zkteco_uid')->get();

        return view('zkteco.unmapped', compact('unmapped', 'employees'));
    }

    public function resolveMapping(Request $request, ZktecoK50Service $service)
    {
        $validated = $request->validate([
            'zkteco_uid' => 'required|integer',
            'user_id' => 'required|exists:users,id',
            'device_id' => 'required|exists:zkteco_devices,id',
        ]);

        $employee = User::findOrFail($validated['user_id']);
        
        $service->resolveMapping($validated['zkteco_uid'], $employee, auth()->user());

        return back()->with('success', 'Mapped. Historical punches processed.');
    }

    public function showImport()
    {
        $logs = AttendanceImportLog::latest()->take(10)->get();
        return view('zkteco.import', compact('logs'));
    }

    public function import(Request $request, ExcelImportService $service)
    {
        $request->validate([
            'import_file' => 'required|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        try {
            $result = $service->import($request->file('import_file'), auth()->user());
            return back()->with('import_result', $result);
        } catch (Exception $e) {
            return back()->with('error', 'Failed to import: ' . $e->getMessage());
        }
    }

    /**
     * Wipe all ZKTeco punch data for a fresh start: raw punches, unmapped UIDs,
     * device-sourced attendance (+ their breaks), and reset device counters.
     * Employees, mappings, and manual attendance are kept. Super admin only.
     */
    public function clearData(Request $request)
    {
        abort_unless($request->user() && $request->user()->hasRole('super_admin'), 403, 'Only a super admin can clear ZKTeco data.');

        $counts = DB::transaction(function () {
            $attIds = AttendanceRecord::where('source', 'zkteco')->pluck('id');
            $breaks = BreakRecord::whereIn('attendance_record_id', $attIds)->delete();
            $att = AttendanceRecord::whereIn('id', $attIds)->delete();

            $punches = ZktecoRawPunch::query()->delete();
            $unmapped = ZktecoUnmapped::query()->delete();

            ZktecoDevice::query()->update([
                'total_records_synced' => 0,
                'last_synced_at' => null,
            ]);

            return compact('att', 'punches', 'unmapped', 'breaks');
        });

        return back()->with('success', "ZKTeco data cleared — {$counts['punches']} punches, {$counts['unmapped']} unmapped, {$counts['att']} attendance records removed. Device counters reset.");
    }
}
