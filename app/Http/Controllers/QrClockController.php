<?php

namespace App\Http\Controllers;

use App\Models\OfficeLocation;
use App\Models\QrClockPoint;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

/**
 * Clocking in by scanning a code on a screen.
 *
 * The code changes every few seconds, so scanning it is evidence of standing
 * in front of the screen — which is the only thing a QR can actually prove. A
 * fixed code proves nothing: it is photographed once and used from a sofa.
 *
 * Attendance itself is not written here. It goes through the same service a
 * manual clock-in uses, so geofencing, re-clocking, breaks and late marks all
 * behave identically however somebody arrives.
 */
class QrClockController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    // ---- admin: managing the points ----

    public function index()
    {
        $this->authorizeManage();

        return view('qr-clock.index', [
            'points' => QrClockPoint::with('officeLocation')->orderBy('name')->get(),
            'locations' => OfficeLocation::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'office_location_id' => 'nullable|exists:office_locations,id',
            'rotate_seconds' => 'nullable|integer|min:10|max:300',
        ]);

        $point = QrClockPoint::create([
            'name' => $validated['name'],
            'office_location_id' => $validated['office_location_id'] ?? null,
            'rotate_seconds' => $validated['rotate_seconds'] ?? 30,
            'secret' => QrClockPoint::newSecret(),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('qr-clock.display', $point)
            ->with('success', 'Point created. Leave this screen open where people clock in.');
    }

    public function destroy(QrClockPoint $point)
    {
        $this->authorizeManage();

        $point->delete();

        return redirect()->route('qr-clock.index')->with('success', 'Point removed.');
    }

    /** The screen itself: a code that redraws as it changes. */
    public function display(QrClockPoint $point)
    {
        $this->authorizeManage();

        return view('qr-clock.display', ['point' => $point]);
    }

    /** What the screen asks for every few seconds. */
    public function token(QrClockPoint $point)
    {
        $this->authorizeManage();

        abort_unless($point->is_active, 404);

        return response()->json([
            'url' => route('qr-clock.scan', [$point, 'c' => $point->currentCode()]),
            'next_in' => $point->window() - (time() % $point->window()),
            'window' => $point->window(),
        ]);
    }

    // ---- the employee, having scanned ----

    public function scan(Request $request, QrClockPoint $point)
    {
        $employee = $request->user();
        $code = (string) $request->query('c', '');

        if (! $point->is_active) {
            return view('qr-clock.result', ['ok' => false, 'point' => $point,
                'message' => 'This point is no longer in use. Ask HR where to clock in.']);
        }

        // An old code means a photograph, a screenshot passed on, or simply a
        // slow walk from the screen. The employee cannot tell which, so the
        // message says what to do rather than accusing them of anything.
        if (! $point->accepts($code)) {
            return view('qr-clock.result', ['ok' => false, 'point' => $point,
                'message' => 'That code has expired. Scan the screen again — it changes every few seconds.']);
        }

        // Today in their own timezone, so somebody on a late shift is not
        // offered "clock in" for a day they are already halfway through.
        $today = \App\Models\AttendanceRecord::where('user_id', $employee->id)
            ->whereDate('date', app(\App\Services\TimezoneService::class)->toUserTime(now(), $employee)->toDateString())
            ->first();

        return view('qr-clock.confirm', [
            'point' => $point,
            'employee' => $employee,
            'code' => $code,
            'record' => $today,
            'action' => ($today && $today->clock_in && ! $today->clock_out) ? 'out' : 'in',
        ]);
    }

    public function punch(Request $request, QrClockPoint $point)
    {
        $employee = $request->user();

        $validated = $request->validate([
            'c' => 'required|string|max:64',
            'action' => 'required|in:in,out',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        // Checked again on the way in: the confirm screen could have sat open.
        if (! $point->is_active || ! $point->accepts($validated['c'])) {
            return view('qr-clock.result', ['ok' => false, 'point' => $point,
                'message' => 'That code has expired. Scan the screen again — it changes every few seconds.']);
        }

        $options = [
            'ip' => $request->ip(),
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
            // So a row can be told apart later from a dashboard clock-in or a
            // device punch, which is the first question asked of any of them.
            'source' => 'qr',
        ];

        try {
            $record = $validated['action'] === 'out'
                ? $this->service->clockOut($employee, $options)
                : $this->service->clockIn($employee, $options);
        } catch (\Throwable $e) {
            return view('qr-clock.result', ['ok' => false, 'point' => $point, 'message' => $e->getMessage()]);
        }

        $point->forceFill(['last_used_at' => now()])->saveQuietly();

        $at = $validated['action'] === 'out' ? $record->clock_out : $record->clock_in;

        return view('qr-clock.result', [
            'ok' => true,
            'point' => $point,
            'message' => ($validated['action'] === 'out' ? 'Clocked out at ' : 'Clocked in at ')
                . app(\App\Services\TimezoneService::class)->toUserTime($at, $employee)->format('H:i'),
            'record' => $record,
        ]);
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Only HR administrators can manage clock-in points.');
    }
}
