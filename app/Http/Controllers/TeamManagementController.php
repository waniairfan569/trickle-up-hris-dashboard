<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Probation;
use App\Models\TimeOffRequest;
use App\Models\User;
use App\Services\AdminReminders;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Team Management hub — one landing page with a tile per team tool (Live Board,
 * Employees Directory, On Leave, …) so the sidebar needs a single link instead
 * of a dropdown. Every tile carries a live count scoped the same way the page it
 * links to is scoped (admins see everyone, managers their own team).
 */
class TeamManagementController extends Controller
{
    public function index(Request $request, AdminReminders $reminders)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $teamIds = $isAdmin ? null : $user->teamMemberIds()->all();

        // Same population as the Live Board: real employees, minus anyone hidden from attendance.
        $employeeUserIds = Employee::real()->pluck('user_id')->filter()
            ->diff(User::attendanceHiddenIds());

        $todayRecords = AttendanceRecord::whereDate('date', Carbon::today())
            ->whereIn('user_id', $employeeUserIds->all())
            ->when(is_array($teamIds), fn ($q) => $q->whereIn('user_id', $teamIds))
            ->get();

        $onLeaveToday = TimeOffRequest::where('status', 'approved')
            ->excludingWorkFromHome()
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->when(is_array($teamIds), fn ($q) => $q->whereIn('user_id', $teamIds))
            ->distinct('user_id')
            ->count('user_id');

        $upcomingLeave = TimeOffRequest::where('status', 'approved')
            ->excludingWorkFromHome()
            ->whereDate('start_date', '>', today())
            ->whereDate('start_date', '<=', today()->copy()->addDays(30))
            ->when(is_array($teamIds), fn ($q) => $q->whereIn('user_id', $teamIds))
            ->count();

        $directoryCount = Employee::real()
            ->whereHas('user', fn ($q) => $isAdmin
                ? $q->where('account_status', '!=', 'deactivated')
                : $q->where('account_status', 'active'))
            ->count();

        $pendingCorrections = AttendanceCorrection::pending()
            ->when(is_array($teamIds), fn ($q) => $q->whereIn('user_id', $teamIds))
            ->count();

        $tiles = [
            [
                'route' => 'attendance.live',
                'icon' => 'activity',
                'tone' => 'brand',
                'title' => 'Live Board',
                'text' => "Who's in, late or absent right now.",
                'stat' => $todayRecords->whereIn('status', ['present', 'late', 'overtime', 'early_departure'])->count(),
                'stat_label' => 'clocked in',
                'meta' => $todayRecords->where('status', 'late')->count() . ' late',
            ],
            [
                'route' => 'employees.index',
                'icon' => 'users',
                'tone' => 'sky',
                'title' => 'Employees Directory',
                'text' => 'Profiles, roles, departments and contact details.',
                'stat' => $directoryCount,
                'stat_label' => 'employees',
                'meta' => null,
            ],
            [
                'route' => 'attendance.on-leave',
                'icon' => 'palmtree',
                'tone' => 'emerald',
                'title' => 'On Leave',
                'text' => 'Approved time off today and over the next 30 days.',
                'stat' => $onLeaveToday,
                'stat_label' => 'off today',
                'meta' => $upcomingLeave . ' upcoming',
            ],
            [
                'route' => 'attendance.team',
                'icon' => 'clipboard-list',
                'tone' => 'violet',
                'title' => 'Team Attendance',
                'text' => 'Attendance history, filters and Excel export.',
                'stat' => $todayRecords->where('status', 'absent')->count(),
                'stat_label' => 'absent today',
                'meta' => null,
            ],
        ];

        if ($isAdmin) {
            $invited = User::where('account_status', 'invited')->count();
            // Slot it in right after Employees Directory — it is the other people-list on this hub.
            array_splice($tiles, 2, 0, [[
                'route' => 'employees.pending-invitations',
                'icon' => 'mail-warning',
                'tone' => 'sky',
                'title' => 'Pending Invitations',
                'text' => 'People invited who have not set up their account yet — resend or cancel.',
                'stat' => $invited,
                'stat_label' => 'awaiting sign-up',
                'meta' => null,
                'badge' => $invited,
            ]]);
        }

        if ($isAdmin && plan_allows('probation')) {
            $active = Probation::where('status', 'active')
                ->whereHas('employee', fn ($q) => $q->where('account_status', '!=', 'deactivated'))
                ->get();

            $tiles[] = [
                'route' => 'probation.index',
                'icon' => 'clipboard-check',
                'tone' => 'amber',
                'title' => 'Probation',
                'text' => 'Track, extend or confirm probation periods.',
                'stat' => $active->count(),
                'stat_label' => 'on probation',
                'meta' => $active->filter(fn ($p) => $p->end_date->lt(now()->startOfDay()))->count() . ' overdue',
            ];
        }

        $tiles[] = [
            'route' => 'attendance.corrections',
            'icon' => 'file-check-2',
            'tone' => 'rose',
            'title' => 'Pending Corrections',
            'text' => 'Clock-in/out fixes waiting for your approval.',
            'stat' => $pendingCorrections,
            'stat_label' => 'awaiting review',
            'meta' => null,
            'badge' => $pendingCorrections,
        ];

        if ($isAdmin) {
            $tiles[] = [
                'route' => 'admin.reminders',
                'icon' => 'bell-ring',
                'tone' => 'indigo',
                'title' => 'Reminders',
                'text' => 'Daily WFH-tomorrow and late-today digests.',
                'stat' => $reminders->wfhOn()->count(),
                'stat_label' => 'WFH tomorrow',
                'meta' => $reminders->lateOn()->count() . ' late today',
            ];
        }

        return view('team.index', compact('tiles'));
    }
}
