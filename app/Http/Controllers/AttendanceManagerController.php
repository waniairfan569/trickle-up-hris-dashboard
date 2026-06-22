<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceManagerController extends Controller
{
    protected AttendanceService $service;

    public function __construct(AttendanceService $service)
    {
        $this->service = $service;
    }

    public function liveBoard(Request $request)
    {
        $user = $request->user();
        
        $query = AttendanceRecord::with('employee.department')->whereDate('date', Carbon::today());

        if (!$user->isAdmin()) {
            $query->forTeam($user);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $records = $query->get()->sortBy(function($record) {
            $order = [
                'absent' => 1,
                'late' => 2,
                'missing_clock_out' => 3,
                'present' => 4,
                'overtime' => 5,
                'early_departure' => 6,
                'on_leave' => 7,
                'public_holiday' => 8,
                'weekend' => 9,
                'correction_pending' => 10,
            ];
            return $order[$record->status] ?? 99;
        });

        // For stat cards we just query again on the same scope but no filters except date
        $statQuery = AttendanceRecord::whereDate('date', Carbon::today());
        if (!$user->isAdmin()) {
            $statQuery->forTeam($user);
        }
        $stats = $statQuery->get();

        $summary = [
            'clocked_in' => $stats->whereIn('status', ['present', 'late', 'overtime', 'early_departure'])->count(),
            'late' => $stats->where('status', 'late')->count(),
            'absent' => $stats->where('status', 'absent')->count(),
            'on_leave' => $stats->where('status', 'on_leave')->count(),
        ];

        $departments = Department::all();

        return view('attendance.live-board', compact('records', 'summary', 'departments'));
    }

    public function teamHistory(Request $request)
    {
        $user = $request->user();
        
        $query = AttendanceRecord::with('employee.department');

        if (!$user->isAdmin()) {
            $query->forTeam($user);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        if ($request->filled('employee_id')) {
            $query->where('user_id', $request->employee_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $records = $query->orderBy('date', 'desc')->paginate(30);
        $departments = Department::all();
        
        // For employee dropdown filter
        $teamMembers = $user->isAdmin() ? User::all() : User::where('manager_id', $user->id)->get();

        return view('attendance.team-history', compact('records', 'departments', 'teamMembers'));
    }

    /** Export the (filtered) team attendance history as CSV. */
    public function teamHistoryExport(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isManager()), 403);

        $query = AttendanceRecord::with('employee.department');
        if (!$user->isAdmin()) {
            $query->forTeam($user);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        if ($request->filled('employee_id')) {
            $query->where('user_id', $request->employee_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->department_id));
        }

        $records = $query->orderBy('date', 'desc')->get();

        $fmt = fn ($min) => $min ? (intdiv((int) $min, 60) . 'h ' . ((int) $min % 60) . 'm') : '0h 0m';

        $callback = function () use ($records, $fmt) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Employee', 'Department', 'Clock In', 'Clock Out', 'Worked', 'Status', 'Late (min)', 'Overtime (min)']);
            foreach ($records as $r) {
                fputcsv($out, [
                    optional($r->date)->format('Y-m-d'),
                    optional($r->employee)->full_name,
                    optional(optional($r->employee)->department)->name,
                    optional($r->clock_in)->format('H:i'),
                    optional($r->clock_out)->format('H:i'),
                    $fmt($r->total_minutes_worked),
                    $r->status,
                    $r->late_minutes ?: 0,
                    $r->overtime_minutes ?: 0,
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'attendance-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function manualEntry(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:Y-m-d\TH:i',
            'clock_out' => 'nullable|date_format:Y-m-d\TH:i',
            'notes' => 'nullable|string',
        ]);

        $record = AttendanceRecord::firstOrCreate(
            ['user_id' => $validated['user_id'], 'date' => $validated['date']]
        );

        $record->clock_in = $validated['clock_in'] ? Carbon::parse($validated['clock_in']) : null;
        $record->clock_out = $validated['clock_out'] ? Carbon::parse($validated['clock_out']) : null;
        $record->notes = $validated['notes'];
        $record->is_manual_entry = true;
        $record->edited_by = auth()->id();
        $record->edited_at = now();

        if ($record->clock_in && $record->clock_out) {
            $record->total_minutes_worked = $record->clock_out->diffInMinutes($record->clock_in);
            $record->status = 'present'; 
            // Minimal recalculation for manual entry. In reality you'd run it through AttendanceService
        }

        $record->save();

        return back()->with('success', 'Attendance record saved manually.');
    }

    public function pendingCorrections(Request $request)
    {
        $user = $request->user();
        $query = AttendanceCorrection::with(['employee', 'record'])->pending();

        if (!$user->isAdmin()) {
            $directReportIds = $user->directReports()->pluck('id');
            $query->whereIn('user_id', $directReportIds);
        }

        $corrections = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('attendance.corrections', compact('corrections'));
    }

    public function approveCorrection(AttendanceCorrection $correction)
    {
        $this->service->approveCorrection($correction, auth()->user());
        return back()->with('success', 'Correction approved successfully.');
    }

    public function rejectCorrection(Request $request, AttendanceCorrection $correction)
    {
        $request->validate(['reviewer_note' => 'required|string']);
        $this->service->rejectCorrection($correction, auth()->user(), $request->reviewer_note);
        return back()->with('success', 'Correction rejected.');
    }
}
