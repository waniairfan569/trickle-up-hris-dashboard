<?php

namespace App\Http\Controllers;

use App\Models\AttendanceReportLog;
use App\Models\AttendanceReportSettings;
use App\Models\CompanyWfhDay;
use App\Models\Employee;
use App\Models\HrDocument;
use App\Models\HrDocumentSigner;
use App\Models\LeaveEncashmentRecord;
use App\Models\LeaveYearSetting;
use App\Models\ReportGeneration;
use App\Models\Shift;
use App\Models\TimeOffPolicy;
use App\Models\TimeTrackingPolicy;
use App\Models\User;
use Carbon\Carbon;

/**
 * Time & Attendance hub — one landing page, three titled tile groups (Time
 * Settings · Attendance · Documents), so the sidebar carries a single link
 * instead of a two-level dropdown. Admin-only, same as the pages it links to.
 */
class TimeAttendanceController extends Controller
{
    public function index()
    {
        $sections = [
            [
                'title' => 'Time Settings',
                'text' => 'Rules for leave, working hours and how time is recorded.',
                'tiles' => $this->timeSettingsTiles(),
            ],
            [
                'title' => 'Attendance',
                'text' => 'Daily reporting, how each employee clocks in, and company-wide remote days.',
                'tiles' => $this->attendanceTiles(),
            ],
        ];

        if (plan_allows('hr_documents')) {
            $sections[] = [
                'title' => 'Documents',
                'text' => 'Lateness reviews, return-to-work letters and other HR paperwork.',
                'tiles' => $this->documentTiles(),
            ];
        }

        return view('time-attendance.index', compact('sections'));
    }

    private function timeSettingsTiles(): array
    {
        $tiles = [
            [
                'route' => 'time-off-policies.index',
                'icon' => 'calendar',
                'tone' => 'brand',
                'title' => 'Time Off Policies',
                'text' => 'Leave types, yearly allowances and who they apply to.',
                'stat' => TimeOffPolicy::active()->count(),
                'stat_label' => 'active policies',
                'meta' => null,
            ],
            [
                'route' => 'leave-year-settings.index',
                'icon' => 'calendar-days',
                'tone' => 'sky',
                'title' => 'Leave Year & Encashment',
                'text' => 'When the leave year resets, carry-forward and encashment rules.',
                'stat' => LeaveYearSetting::where('is_active', true)->count(),
                'stat_label' => 'active settings',
                'meta' => LeaveYearSetting::where('is_active', true)->where('encashment_enabled', true)->count() . ' with encashment',
            ],
        ];

        if (plan_allows('leave_encashment')) {
            $pending = LeaveEncashmentRecord::where('status', 'pending')->count();
            $tiles[] = [
                'route' => 'leave-encashments.index',
                'icon' => 'wallet',
                'tone' => 'emerald',
                'title' => 'Encashment Records',
                'text' => 'Unused-leave payouts — approve, mark paid, or reject.',
                'stat' => $pending,
                'stat_label' => 'pending',
                'meta' => LeaveEncashmentRecord::where('status', 'approved')->count() . ' approved, unpaid',
                'badge' => $pending,
            ];
        }

        $tiles[] = [
            'route' => 'time-tracking-policies.index',
            'icon' => 'clock',
            'tone' => 'violet',
            'title' => 'Time Tracking',
            'text' => 'How time is recorded — clock in/out vs. timesheets, geofencing, reminders.',
            'stat' => TimeTrackingPolicy::count(),
            'stat_label' => 'policies',
            'meta' => null,
        ];

        if (plan_allows('shifts')) {
            $default = Shift::where('is_active', true)->where('is_default', true)->first();
            $tiles[] = [
                'route' => 'shifts.index',
                'icon' => 'calendar-clock',
                'tone' => 'amber',
                'title' => 'Shift Management',
                'text' => 'Working hours, breaks and days — assigned per employee, drives lateness.',
                'stat' => Shift::where('is_active', true)->count(),
                'stat_label' => 'active shifts',
                'meta' => $default ? 'Default: ' . $default->name : null,
            ];
        }

        return $tiles;
    }

    private function attendanceTiles(): array
    {
        $tiles = [];

        if (plan_allows('reports')) {
            $settings = AttendanceReportSettings::getSettings();
            $lastSent = AttendanceReportLog::where('status', 'sent')->max('sent_at');
            $tiles[] = [
                'route' => 'attendance-reports.settings',
                'icon' => 'bar-chart-3',
                'tone' => 'brand',
                'title' => 'Attendance Reports',
                'text' => 'Daily attendance digest emailed to admins and managers.',
                'stat' => $settings->is_enabled ? 'On' : 'Off',
                'stat_label' => 'daily report',
                'meta' => $lastSent ? 'Last sent ' . Carbon::parse($lastSent)->diffForHumans() : 'Never sent',
            ];
        }

        // Same population as the Attendance Mode page: real, non-deactivated employees.
        $systemUserIds = Employee::where('is_system', true)->pluck('user_id')->filter();
        $modes = User::where('account_status', '!=', 'deactivated')
            ->whereNotIn('id', $systemUserIds->all())
            ->get(['id', 'attendance_mode'])
            ->groupBy(fn ($u) => $u->attendance_mode ?? 'biometric')
            ->map->count();

        $tiles[] = [
            'route' => 'employees.attendance-mode',
            'icon' => 'sliders-horizontal',
            'tone' => 'sky',
            'title' => 'Attendance Mode',
            'text' => 'Who clocks in on the biometric device and who clocks in remotely.',
            'stat' => $modes->get('remote', 0),
            'stat_label' => 'remote',
            'meta' => $modes->get('biometric', 0) . ' biometric',
        ];

        if (plan_allows('report_generator')) {
            $lastRun = ReportGeneration::max('created_at');
            $tiles[] = [
                'route' => 'reports.generate',
                'icon' => 'file-bar-chart-2',
                'tone' => 'violet',
                'title' => 'Report Generator',
                'text' => 'On-demand attendance and leave PDF reports — one employee or everyone.',
                'stat' => ReportGeneration::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count(),
                'stat_label' => 'generated this month',
                'meta' => $lastRun ? 'Last run ' . Carbon::parse($lastRun)->diffForHumans() : 'Never run',
            ];
        }

        $tiles[] = [
            'route' => 'company-wfh-days.index',
            'icon' => 'house-wifi',
            'tone' => 'emerald',
            'title' => 'Company WFH Days',
            'text' => 'Dates when the whole company works from home.',
            'stat' => CompanyWfhDay::whereDate('date', '>=', today())->count(),
            'stat_label' => 'upcoming',
            'meta' => CompanyWfhDay::whereYear('date', now()->year)->count() . ' this year',
        ];

        return $tiles;
    }

    private function documentTiles(): array
    {
        $awaiting = HrDocumentSigner::whereNull('signed_at')
            ->whereHas('document', fn ($q) => $q->where('status', 'sent'))
            ->count();

        return [[
            'route' => 'hr-documents.index',
            'icon' => 'file-signature',
            'tone' => 'rose',
            'title' => 'Documents',
            'text' => 'Generate HR documents from templates and send them for signature.',
            'stat' => HrDocument::where('status', 'sent')->count(),
            'stat_label' => 'out for signature',
            'meta' => $awaiting . ' signatures pending',
        ]];
    }
}
