<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use App\Services\GeofenceService;
use App\Exceptions\GeofenceException;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected AttendanceService $service;

    public function __construct(AttendanceService $service)
    {
        $this->service = $service;
    }

    public function clockIn(Request $request)
    {
        // Biometric-mode users must punch on the ZKTeco device — the dashboard
        // clock-in is hidden for them, so block the endpoint server-side too.
        if (!$request->user()->usesDashboardClockIn()) {
            return response()->json([
                'success' => false,
                'message' => 'Your attendance is recorded on the biometric device. Please clock in there.',
            ], 403);
        }

        $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $record = $this->service->clockIn($request->user(), [
                'ip' => $request->ip(),
                'lat' => $request->lat,
                'lng' => $request->lng,
            ]);
            
            return response()->json([
                'success' => true,
                'status' => $record->status,
                'clocked_in_at' => $record->clock_in->format('H:i'),
                'message' => 'Clocked in successfully',
            ]);
        } catch (GeofenceException $e) {
            return response()->json([
                'success' => false, 
                'error' => 'geofence', 
                'message' => $e->userMessage
            ], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function clockOut(Request $request)
    {
        if (!$request->user()->usesDashboardClockIn()) {
            return response()->json([
                'success' => false,
                'message' => 'Your attendance is recorded on the biometric device. Please clock out there.',
            ], 403);
        }

        $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $record = $this->service->clockOut($request->user(), [
                'ip' => $request->ip(),
                'lat' => $request->lat,
                'lng' => $request->lng,
            ]);

            return response()->json([
                'success' => true,
                'status' => $record->status,
                'hours_worked' => $record->hours_worked,
                'message' => 'Clocked out successfully',
            ]);
        } catch (GeofenceException $e) {
            return response()->json([
                'success' => false, 
                'error' => 'geofence', 
                'message' => $e->userMessage
            ], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function officeStatus(GeofenceService $geofenceService, Request $request)
    {
        return response()->json(
            $geofenceService->getStatusForFrontend($request->user())
        );
    }

    public function startBreak(Request $request)
    {
        try {
            $break = $this->service->startBreak($request->user(), [
                'break_type' => $request->break_type ?? 'short',
            ]);

            return response()->json([
                'success' => true,
                'break_start' => $break->break_start->format('H:i'),
                'message' => 'Break started',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function endBreak(Request $request)
    {
        try {
            $break = $this->service->endBreak($request->user());

            return response()->json([
                'success' => true,
                'duration_minutes' => $break->duration_minutes,
                'message' => 'Break ended',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function todayStatus(Request $request)
    {
        return response()->json($this->service->getTodayStatus($request->user()));
    }

    public function myHistory(Request $request)
    {
        $user = $request->user();
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $date = Carbon::createFromDate($year, $month, 1);

        $records = AttendanceRecord::forUser($user)
            ->with('employee')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'desc')
            ->paginate(31);

        $summary = [
            'present' => AttendanceRecord::forUser($user)->whereMonth('date', $month)->whereYear('date', $year)->present()->count(),
            'late' => AttendanceRecord::forUser($user)->whereMonth('date', $month)->whereYear('date', $year)->late()->count(),
            'absent' => AttendanceRecord::forUser($user)->whereMonth('date', $month)->whereYear('date', $year)->absent()->count(),
            'total_hours' => floor(AttendanceRecord::forUser($user)->whereMonth('date', $month)->whereYear('date', $year)->sum('total_minutes_worked') / 60),
        ];

        // Weekly time-tracking view (Mon–Sun day blocks).
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->week)->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekDays = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
        $weekRecords = AttendanceRecord::forUser($user)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        $weekTotalMinutes = (int) $weekRecords->sum('total_minutes_worked');

        return view('attendance.my-history', compact('records', 'date', 'summary', 'weekStart', 'weekDays', 'weekRecords', 'weekTotalMinutes'));
    }

    public function submitCorrection(Request $request)
    {
        $validated = $request->validate([
            'correction_date' => 'required|date',
            'requested_clock_in' => 'nullable|date_format:H:i',
            'requested_clock_out' => 'nullable|date_format:H:i|after:requested_clock_in',
            'reason' => 'required|string',
        ]);

        $user = $request->user();
        $date = Carbon::parse($validated['correction_date']);

        $record = AttendanceRecord::firstOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            ['status' => 'correction_pending']
        );

        $reqIn = $validated['requested_clock_in'] ? Carbon::parse($date->toDateString() . ' ' . $validated['requested_clock_in']) : null;
        $reqOut = $validated['requested_clock_out'] ? Carbon::parse($date->toDateString() . ' ' . $validated['requested_clock_out']) : null;

        AttendanceCorrection::create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'correction_date' => $date,
            'requested_clock_in' => $reqIn,
            'requested_clock_out' => $reqOut,
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Correction submitted successfully and is pending approval.');
    }
}
