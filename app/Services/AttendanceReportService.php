<?php

namespace App\Services;

use App\Mail\DailyAttendanceReportMail;
use App\Models\AttendanceRecord;
use App\Models\AttendanceReportLog;
use App\Models\AttendanceReportSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AttendanceReportService
{
    public function __construct(private EmployeeAccessService $access)
    {
    }

    /** Active users with a given role slug (custom role system). */
    private function usersByRole(string $slug)
    {
        return User::active()
            ->whereHas('roles', fn ($q) => $q->where('slug', $slug))
            ->get();
    }

    private function hoursLabel($minutes): string
    {
        $m = (int) $minutes;
        if ($m <= 0) {
            return '—';
        }

        return intdiv($m, 60) . 'h ' . ($m % 60) . 'm';
    }

    /** Collect attendance data for a date (optionally scoped to a manager's team). */
    public function getReportData(Carbon $date, ?User $manager = null): array
    {
        $query = AttendanceRecord::with(['employee.department'])
            ->whereDate('date', $date->toDateString());

        $reportingIds = null;
        if ($manager) {
            $reportingIds = $this->access->getReportingLine($manager)->pluck('id');
            $query->whereIn('user_id', $reportingIds->all());
        }

        // Only real employees are expected to attend — system/owner accounts
        // are never counted absent, and anyone hidden from attendance is skipped.
        $employeeUserIds = \App\Models\Employee::real()->pluck('user_id')->filter()
            ->diff(User::attendanceHiddenIds());

        $records = $query->get()->filter(fn ($r) => $employeeUserIds->contains($r->user_id))->values();

        $activeUsers = User::active()
            ->whereIn('id', $employeeUserIds->all())
            ->when($reportingIds, fn ($q) => $q->whereIn('id', $reportingIds->all()))
            ->with('department')
            ->get();

        // Authoritative "on leave today" = approved leave covering the date.
        // Work-From-Home is excluded — those employees are working (remotely),
        // not off, so they stay in the expected-present pool.
        $onLeaveRequests = \App\Models\TimeOffRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->whereDoesntHave('policy', fn ($q) => $q->where(fn ($q2) => $q2
                ->whereRaw('LOWER(name) like ?', ['%work from home%'])
                ->orWhereRaw('LOWER(name) like ?', ['%wfh%'])))
            ->when($reportingIds, fn ($q) => $q->whereIn('user_id', $reportingIds->all()))
            ->with(['employee.department', 'policy'])
            ->get();
        $onLeaveUserIds = $onLeaveRequests->pluck('user_id')->unique();

        $presentIds = $records->pluck('user_id')->all();
        // People on leave are NOT absent, even if they have no record / an absent row.
        // Non-working days (per the employee's schedule, else the company-wide
        // working-days setting) are off, not absent.
        $reportSettings = AttendanceReportSettings::getSettings();
        $shiftSvc = app(\App\Services\ShiftService::class);
        $tzSvc = app(\App\Services\TimezoneService::class);
        $now = Carbon::now();

        // Someone with no attendance record is "absent" ONLY on a working day AND
        // once their shift/schedule start time has actually passed. An employee whose
        // shift begins at 13:30 is NOT absent when the report runs at 10:00 — their
        // working day hasn't started yet. Today's assigned shift drives both the
        // working-day and the start-time check; we fall back to the work schedule /
        // company working-days setting when no shift is assigned. (For past dates the
        // start time is long gone, so genuine no-shows still count as absent.)
        $noRecordUsers = $activeUsers
            ->whereNotIn('id', $presentIds)
            ->whereNotIn('id', $onLeaveUserIds->all())
            ->filter(function ($u) use ($shiftSvc, $tzSvc, $reportSettings, $date, $now) {
                $shift = $shiftSvc->getShiftForUserOnDate($u, $date->copy());
                if ($shift) {
                    $startStr = $shift->start_time;               // has a shift today → working day
                } else {
                    $isWorkingDay = (method_exists($u, 'workSchedule') && $u->workSchedule)
                        ? $u->workSchedule->isWorkingDay($date)
                        : $reportSettings->isWorkingDay($date);
                    if (!$isWorkingDay) {
                        return false;                             // off today → not absent
                    }
                    $startStr = optional($u->workSchedule)->start_time ?: (config('attendance.late_after') ?: '09:30');
                }

                // Not absent until their shift start has passed (in their own timezone).
                $nowUser = $tzSvc->toUserTime($now, $u);
                $start = Carbon::parse($date->toDateString() . ' ' . $startStr, $nowUser->getTimezone());
                return $nowUser->greaterThanOrEqualTo($start);
            });

        $absentRecords = $records->where('status', 'absent')
            ->filter(fn ($r) => !$onLeaveUserIds->contains($r->user_id));

        return [
            'date' => $date,
            'summary' => [
                'total_expected' => $activeUsers->count(),
                'present' => $records->whereIn('status', ['present', 'late', 'overtime', 'early_departure'])->count(),
                'late' => $records->where('status', 'late')->count(),
                'absent' => $absentRecords->count() + $noRecordUsers->count(),
                'on_leave' => $onLeaveUserIds->count(),
                'overtime' => $records->where('status', 'overtime')->count(),
                'early_departure' => $records->where('status', 'early_departure')->count(),
                'missing_clock_out' => $records->where('status', 'missing_clock_out')->count(),
            ],
            'late_employees' => $records->where('status', 'late')->map(fn ($r) => [
                'name' => optional($r->employee)->full_name ?? 'Unknown',
                'department' => optional(optional($r->employee)->department)->name,
                // Timezone-aware (employee's local time) — matches the Live Board.
                'clock_in' => $r->clock_in_local ?? '—',
                'late_minutes' => (int) $r->late_minutes,
                'late_label' => ((int) $r->late_minutes) . ' min late',
            ])->sortByDesc('late_minutes')->values()->all(),
            'absent_employees' => $absentRecords->map(fn ($r) => [
                'name' => optional($r->employee)->full_name ?? 'Unknown',
                'department' => optional(optional($r->employee)->department)->name,
            ])->concat($noRecordUsers->map(fn ($u) => [
                'name' => $u->full_name,
                'department' => optional($u->department)->name,
            ]))->values()->all(),
            'on_leave_employees' => $onLeaveRequests->filter(fn ($r) => $r->employee)->map(fn ($r) => [
                'name' => optional($r->employee)->full_name ?? 'Unknown',
                'department' => optional(optional($r->employee)->department)->name,
                'note' => $r->duration_type === 'hourly'
                    ? ($r->time_range ?? 'Hourly')
                    : ($r->is_half_day ? ('Half day' . ($r->half_day_period ? ' (' . ucfirst($r->half_day_period) . ')' : '')) : 'Full day'),
                'policy' => optional($r->policy)->name,
            ])->sortBy('name')->values()->all(),
            'full_table' => $records->map(fn ($r) => [
                'name' => optional($r->employee)->full_name ?? 'Unknown',
                'department' => optional(optional($r->employee)->department)->name,
                'clock_in' => $r->clock_in_local ?? '—',
                'clock_out' => $r->clock_out_local ?? '—',
                'hours_worked' => $this->hoursLabel($r->total_minutes_worked),
                'late_minutes' => (int) $r->late_minutes,
                'overtime_minutes' => (int) $r->overtime_minutes,
                'status' => $r->status,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $r->status)),
            ])->sortBy('name')->values()->all(),
        ];
    }

    /** Send the daily report to the configured recipients; logs the outcome. */
    public function sendDailyReport(Carbon $date, bool $isManual = false, ?User $triggeredBy = null): array
    {
        $settings = AttendanceReportSettings::getSettings();

        if (!$settings->is_enabled) {
            return ['skipped' => true, 'reason' => 'Reports disabled'];
        }
        if (!$isManual && !$settings->isWorkingDay($date)) {
            return ['skipped' => true, 'reason' => 'Not a working day'];
        }

        $sentTo = [];
        $companyData = $this->getReportData($date);

        $deliver = function ($email, array $data, ?User $recipient) use ($settings, &$sentTo) {
            if (!$email) {
                return;
            }
            try {
                Mail::to($email)->send(new DailyAttendanceReportMail($data, $settings, $recipient));
                $sentTo[] = $email;
            } catch (\Throwable $e) {
                Log::warning('Daily attendance report failed for ' . $email . ': ' . $e->getMessage());
            }
        };

        if ($settings->send_to_super_admin) {
            foreach ($this->usersByRole('super_admin') as $admin) {
                $deliver($admin->email, $companyData, $admin);
            }
        }

        if ($settings->send_to_hr_admin) {
            foreach ($this->usersByRole('hr_admin') as $hr) {
                $deliver($hr->email, $companyData, $hr);
            }
        }

        if ($settings->send_to_managers) {
            $managers = User::active()
                ->whereHas('roles', fn ($q) => $q->where('slug', 'manager'))
                ->where(fn ($q) => $q->has('directReports')->orHas('additionalTeam'))
                ->get();
            foreach ($managers as $manager) {
                $data = $this->getReportData($date, $manager);
                if ($data['summary']['total_expected'] > 0) {
                    $deliver($manager->email, $data, $manager);
                }
            }
        }

        foreach ($settings->additional_emails ?? [] as $email) {
            $deliver($email, $companyData, null);
        }

        AttendanceReportLog::create([
            'report_date' => $date->toDateString(),
            'sent_at' => now(),
            'sent_to' => $sentTo,
            'total_employees' => $companyData['summary']['total_expected'],
            'present_count' => $companyData['summary']['present'],
            'late_count' => $companyData['summary']['late'],
            'absent_count' => $companyData['summary']['absent'],
            'on_leave_count' => $companyData['summary']['on_leave'],
            'status' => 'sent',
            'triggered_by' => $isManual ? 'manual' : 'scheduled',
            'triggered_by_user_id' => $triggeredBy?->id,
        ]);

        return ['sent' => true, 'recipients' => count($sentTo), 'emails' => $sentTo];
    }
}
