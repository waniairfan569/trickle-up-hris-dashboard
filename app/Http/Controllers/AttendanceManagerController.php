<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\TimeOffRequest;
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
        
        // Only real employees — exclude the company/workspace account (no Employee record).
        $employeeUserIds = \App\Models\Employee::pluck('user_id')->filter();

        $query = AttendanceRecord::with('employee.department')
            ->whereDate('date', Carbon::today())
            ->whereIn('user_id', $employeeUserIds->all());

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
        $statQuery = AttendanceRecord::whereDate('date', Carbon::today())
            ->whereIn('user_id', $employeeUserIds->all());
        if (!$user->isAdmin()) {
            $statQuery->forTeam($user);
        }
        $stats = $statQuery->get();

        // Authoritative on-leave = approved leave covering today (matches the strip).
        $onLeaveIds = $this->onLeaveUserIds($user);

        // Reflect on-leave in the live table too (a no-show on leave isn't absent).
        $records->each(function ($r) use ($onLeaveIds) {
            if ($onLeaveIds->contains($r->user_id) && in_array($r->status, ['absent', 'missing_clock_out'], true)) {
                $r->status = 'on_leave';
            }
        });

        $summary = [
            'clocked_in' => $stats->whereIn('status', ['present', 'late', 'overtime', 'early_departure'])->count(),
            'late' => $stats->where('status', 'late')->count(),
            // On-leave people are never counted absent.
            'absent' => $stats->where('status', 'absent')->filter(fn ($r) => !$onLeaveIds->contains($r->user_id))->count(),
            'on_leave' => $onLeaveIds->count(),
        ];

        $departments = Department::all();
        $onLeavePeople = $this->onLeavePeople($user);

        return view('attendance.live-board', compact('records', 'summary', 'departments', 'onLeavePeople'));
    }

    /** Approved-leave people covering today, scoped to a manager's team. */
    private function onLeavePeople(User $user)
    {
        $ids = $user->isAdmin() ? null : $user->directReports()->pluck('id')->all();
        return TimeOffRequest::onLeaveToday($ids);
    }

    /** User IDs on approved leave today (scoped to a manager's team). */
    private function onLeaveUserIds(User $user)
    {
        $scope = $user->isAdmin() ? null : $user->directReports()->pluck('id')->all();

        return TimeOffRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', Carbon::today())
            ->whereDate('end_date', '>=', Carbon::today())
            ->when(is_array($scope), fn ($q) => $q->whereIn('user_id', $scope))
            ->pluck('user_id')
            ->unique();
    }

    /**
     * Re-evaluate attendance status against the current late rule (09:30 cutoff)
     * for a given day — fixes records created before the rule existed, regardless
     * of source (device or app). Admin only. Defaults to today.
     */
    public function recalcLate(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $date = $request->input('date', today()->toDateString());
        $count = 0;
        $changed = 0;

        AttendanceRecord::with('employee')
            ->whereDate('date', $date)
            ->whereNotNull('clock_in')
            ->chunkById(300, function ($records) use (&$count, &$changed) {
                foreach ($records as $r) {
                    $before = $r->status . '|' . $r->late_minutes;
                    $r->recalculate();
                    $count++;
                    if ($before !== $r->status . '|' . $r->late_minutes) {
                        $r->save();
                        $changed++;
                    }
                }
            });

        return back()->with('success', "Re-checked {$count} record(s) for " . \Carbon\Carbon::parse($date)->format('d M Y') . " against the 09:30 rule — {$changed} updated.");
    }

    /** Bulk backfill form — add attendance for many employees on a date. */
    public function backfillForm(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);
        return view('attendance.backfill');
    }

    /**
     * Create/refresh attendance for every active employee on a date with a
     * fixed clock-in (and optional clock-out). Everyone is marked Present with
     * no late minutes. Records are saved as source 'manual' so the ZKTeco
     * Rebuild / Clear actions never remove them. Employees already on approved
     * leave that day are skipped; existing records are only overwritten when
     * asked. Admins only.
     */
    public function backfill(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'date' => 'required|date',
            'clock_in' => 'required|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'overwrite' => 'nullable|boolean',
        ]);

        if (!empty($validated['clock_out']) && $validated['clock_out'] <= $validated['clock_in']) {
            return back()->with('error', 'Clock-out must be after clock-in.')->withInput();
        }

        $tz = app(\App\Services\TimezoneService::class);
        $canonical = config('app.timezone') ?: \App\Services\TimezoneService::FALLBACK_TIMEZONE;
        $date = $validated['date'];
        $overwrite = $request->boolean('overwrite');

        $users = User::where('account_status', '!=', 'deactivated')->get();
        $created = 0; $updated = 0; $skipped = 0; $onLeave = 0;

        foreach ($users as $user) {
            // Keep people who are on approved leave that day as-is.
            $isOnLeave = TimeOffRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();
            if ($isOnLeave) { $onLeave++; continue; }

            // Include soft-deleted rows: the UNIQUE(user_id,date) index counts
            // them, so a trashed record must be restored & reused, not re-inserted.
            $existing = AttendanceRecord::withTrashed()->where('user_id', $user->id)->whereDate('date', $date)->first();
            $liveExisted = $existing && !$existing->trashed();
            if ($liveExisted && !$overwrite) { $skipped++; continue; }

            $userTz = $tz->getEffectiveTimezone($user);
            $clockIn = Carbon::parse($date . ' ' . $validated['clock_in'], $userTz)->setTimezone($canonical);
            $clockOut = !empty($validated['clock_out'])
                ? Carbon::parse($date . ' ' . $validated['clock_out'], $userTz)->setTimezone($canonical)
                : null;

            $record = $existing ?: new AttendanceRecord(['user_id' => $user->id, 'date' => $date]);
            if ($record->exists && $record->trashed()) {
                $record->restore();
            }
            $record->clock_in = $clockIn;
            $record->clock_out = $clockOut;
            $record->status = 'present';
            $record->late_minutes = 0;
            $record->overtime_minutes = 0;
            $record->source = 'manual';
            $record->edited_by = $request->user()->id;
            $record->edited_at = now();
            $record->total_minutes_worked = $clockOut ? max(0, (int) round($clockIn->diffInMinutes($clockOut))) : 0;
            $record->save();

            $liveExisted ? $updated++ : $created++;
        }

        return back()->with('success', "Backfill for " . \Carbon\Carbon::parse($date)->format('d M Y') . " — {$created} added, {$updated} updated, {$skipped} skipped (already had a record), {$onLeave} on leave.");
    }

    /** Dedicated "On Leave" page: who's out today + upcoming approved leave. */
    public function onLeave(Request $request)
    {
        $user = $request->user();
        $onLeavePeople = $this->onLeavePeople($user);
        $ids = $user->isAdmin() ? null : $user->directReports()->pluck('id')->all();

        $upcoming = TimeOffRequest::where('status', 'approved')
            ->whereDate('start_date', '>', today())
            ->whereDate('start_date', '<=', today()->copy()->addDays(30))
            ->when(is_array($ids), fn ($q) => $q->whereIn('user_id', $ids))
            ->with(['employee:id,first_name,last_name,avatar_url', 'policy:id,name'])
            ->orderBy('start_date')
            ->get();

        return view('attendance.on-leave', compact('onLeavePeople', 'upcoming'));
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
        $onLeavePeople = $this->onLeavePeople($user);

        return view('attendance.team-history', compact('records', 'departments', 'teamMembers', 'onLeavePeople'));
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

        $record = AttendanceRecord::findOrNewForDate($validated['user_id'], $validated['date']);

        $record->clock_in = $validated['clock_in'] ? Carbon::parse($validated['clock_in']) : null;
        $record->clock_out = $validated['clock_out'] ? Carbon::parse($validated['clock_out']) : null;
        $record->notes = $validated['notes'];
        $record->is_manual_entry = true;
        $record->edited_by = auth()->id();
        $record->edited_at = now();

        if ($record->clock_in && $record->clock_out) {
            $record->total_minutes_worked = max(0, (int) round($record->clock_in->diffInMinutes($record->clock_out)));
            $record->status = 'present';
            // Minimal recalculation for manual entry. In reality you'd run it through AttendanceService
        }

        $record->save();

        return back()->with('success', 'Attendance record saved manually.');
    }

    /**
     * Admin edit of a single record's clock-in / clock-out time. Times come in
     * as the employee's local wall-clock (datetime-local) and are stored in the
     * canonical timezone. Status / late / worked minutes are recomputed.
     */
    public function updateTimes(Request $request, AttendanceRecord $record)
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin(), 403);

        $validated = $request->validate([
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
        ]);

        $tz = app(\App\Services\TimezoneService::class);
        $userTz = $tz->getEffectiveTimezone($record->employee);
        $canonical = config('app.timezone') ?: \App\Services\TimezoneService::FALLBACK_TIMEZONE;
        // Always apply the times to THIS record's own date (not today / not a
        // date carried by a datetime picker) — the times are entered in the
        // employee's timezone and stored canonical.
        $date = $record->date->format('Y-m-d');

        $record->clock_in = !empty($validated['clock_in'])
            ? Carbon::parse($date . ' ' . $validated['clock_in'], $userTz)->setTimezone($canonical) : null;
        $record->clock_out = !empty($validated['clock_out'])
            ? Carbon::parse($date . ' ' . $validated['clock_out'], $userTz)->setTimezone($canonical) : null;

        if ($record->clock_in && $record->clock_out && $record->clock_out->lessThan($record->clock_in)) {
            return back()->with('error', 'Clock-out cannot be before clock-in.');
        }

        $record->edited_by = $user->id;
        $record->edited_at = now();
        $record->recalculate();
        $record->save();

        return back()->with('success', 'Attendance time updated for ' . $record->employee->full_name . '.');
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
