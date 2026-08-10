<?php

namespace App\Http\Controllers;

use App\Models\AttendanceReportLog;
use App\Models\AttendanceReportSettings;
use App\Services\AttendanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    public function settings()
    {
        $settings = AttendanceReportSettings::getSettings();
        $logs = AttendanceReportLog::with('triggeredBy')->orderByDesc('report_date')->orderByDesc('id')->limit(30)->get();

        $counts = [
            'super_admin' => $this->roleCount('super_admin'),
            'hr_admin' => $this->roleCount('hr_admin'),
            'manager' => $this->roleCount('manager'),
        ];

        return view('attendance-reports.settings', compact('settings', 'logs', 'counts'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'send_time' => 'required|date_format:H:i',
            'timezone' => 'required|timezone',
            'working_days' => 'nullable|array',
            'working_days.*' => 'string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'late_threshold_minutes' => 'required|integer|min:0|max:60',
            'report_subject_template' => 'nullable|string|max:255',
            'additional_emails_raw' => 'nullable|string',
        ]);

        $emails = collect(preg_split('/[\r\n,]+/', (string) $request->input('additional_emails_raw', '')))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()->all();

        AttendanceReportSettings::getSettings()->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'send_time' => $validated['send_time'] . ':00',
            'timezone' => $validated['timezone'],
            'working_days' => $validated['working_days'] ?? [],
            'send_to_super_admin' => $request->boolean('send_to_super_admin'),
            'send_to_hr_admin' => $request->boolean('send_to_hr_admin'),
            'send_to_managers' => $request->boolean('send_to_managers'),
            'additional_emails' => $emails,
            'include_late' => $request->boolean('include_late'),
            'include_absent' => $request->boolean('include_absent'),
            'include_early_departure' => $request->boolean('include_early_departure'),
            'include_overtime' => $request->boolean('include_overtime'),
            'include_on_leave' => $request->boolean('include_on_leave'),
            'include_full_table' => $request->boolean('include_full_table'),
            'attach_excel' => $request->boolean('attach_excel'),
            'late_threshold_minutes' => $validated['late_threshold_minutes'],
            'report_subject_template' => $validated['report_subject_template'] ?: 'Daily Attendance Report — {{date}}',
        ]);

        return back()->with('success', 'Report settings updated.');
    }

    public function sendManual(Request $request, AttendanceReportService $service)
    {
        $request->validate(['date' => 'required|date|before_or_equal:today']);

        $result = $service->sendDailyReport(Carbon::parse($request->date), true, auth()->user());

        if (!empty($result['skipped'])) {
            return back()->with('error', 'Not sent: ' . $result['reason']);
        }

        return back()->with('success', "Report sent to {$result['recipients']} recipient(s).");
    }

    public function previewReport(AttendanceReportService $service)
    {
        $data = $service->getReportData(Carbon::today());
        $settings = AttendanceReportSettings::getSettings();

        return view('emails.daily-attendance-report', [
            'data' => $data,
            'settings' => $settings,
            'recipient' => auth()->user(),
        ]);
    }

    private function roleCount(string $slug): int
    {
        return \App\Models\User::where('account_status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('slug', $slug))
            ->count();
    }
}
