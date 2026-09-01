<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function index(Request $request)
    {
        return $this->handle($request);
    }

    /**
     * Handle the single-action controller invocation.
     */
    public function __invoke(Request $request)
    {
        return $this->handle($request);
    }

    /**
     * Route user based on their HRIS roles.
     */
    protected function handle(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Platform operators don't belong to any company — send them to their own
        // console, never the company dashboard.
        if ($user->isOperator()) {
            return redirect()->route('operator.index');
        }

        // Shared widgets shown on every dashboard (calendar + time-off balances).
        // Show anything still on the calendar: approved leave AND requests still
        // awaiting a decision (pending). Rejected/cancelled aren't "upcoming".
        $upcomingTimeOff = \App\Models\TimeOffRequest::where('user_id', $user->id)
            ->whereDate('end_date', '>=', today())
            ->whereIn('status', ['approved', 'pending'])
            ->orderBy('start_date', 'asc')
            ->get();

        // Work From Home is not "out of office" — those people are working.
        $outOfOfficeCount = \App\Models\TimeOffRequest::where('status', 'approved')
            ->excludingWorkFromHome()
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();

        $timeOffBalances = \App\Models\TimeOffBalance::where('user_id', $user->id)
            ->where('year', date('Y'))
            ->with('policy')
            ->get()
            // Only policies the admin chose to surface on the dashboard (a balance
            // with no policy, or a legacy policy, defaults to shown).
            ->filter(fn ($b) => optional($b->policy)->show_on_dashboard ?? true)
            ->values();

        $celebrations = $this->upcomingCelebrations();
        $events = $this->companyEvents($user);
        $holidays = $this->companyHolidays();
        $outOfOffice = $this->outOfOffice();
        $onLeavePeople = \App\Models\TimeOffRequest::onLeaveToday();
        $announcements = $this->dashboardAnnouncements();

        // Next few published events this user can see (pinned first) — powers the
        // "Upcoming events" dashboard card.
        $upcomingEvents = \App\Models\Event::active()->visibleTo($user)
            ->where(function ($q) {
                $q->whereDate('date', '>=', today())->orWhereDate('end_date', '>=', today());
            })
            ->orderByDesc('is_pinned')->orderBy('date')
            ->take(3)->get();

        // Admin events summary card.
        $eventStats = null;
        if ($user->isAdmin()) {
            $eventStats = [
                'this_month' => \App\Models\Event::active()
                    ->whereBetween('date', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()])
                    ->count(),
                'drafts' => \App\Models\Event::active()->where('is_published', false)->count(),
            ];
        }

        $shared = compact('upcomingTimeOff', 'outOfOfficeCount', 'outOfOffice', 'timeOffBalances', 'celebrations', 'events', 'holidays', 'onLeavePeople', 'upcomingEvents', 'eventStats', 'announcements');

        // super_admin/hr_admin → dashboard.admin
        if ($user->isAdmin()) {
            return view('dashboard.admin', $shared);
        }

        // manager → dashboard.manager
        if ($user->isManager()) {
            return view('dashboard.manager', $shared);
        }

        // employee/restricted → dashboard.employee
        return view('dashboard.employee', $shared);
    }

    /**
     * Active, non-expired announcements for the "day at a glance" widget —
     * pinned first, then newest. Carries pre-rendered safe HTML so the View
     * popup can show links + line breaks without re-querying.
     */
    protected function dashboardAnnouncements()
    {
        return \App\Models\Announcement::active()->with('creator')
            ->orderByDesc('is_pinned')->latest()->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body_html' => (string) $a->bodyHtml(),
                'author' => optional($a->creator)->full_name ?? 'Admin',
                'posted_label' => $a->created_at->format('d M Y'),
                'expires_label' => optional($a->expires_at)->format('d M Y'),
                'pinned' => (bool) $a->is_pinned,
            ])->values();
    }

    /**
     * Active company events in a window — ONE entry per event carrying its
     * start and end dates. A multi-day event is a single ranged row (the client
     * renders "28 Jul – 15 Aug"), not one row per day it covers.
     */
    protected function companyEvents($user = null)
    {
        $windowStart = today()->copy()->subDays(31);
        $windowEnd = today()->copy()->addDays(180);

        $query = \App\Models\Event::active();
        // Employees only see events published to them; admins see all active events
        // (published + drafts) so they can preview what's on the calendar.
        if ($user && ! $user->isAdmin()) {
            $query->published()->visibleTo($user);
        }

        return $query
            ->whereDate('date', '<=', $windowEnd)
            ->where(function ($q) use ($windowStart) {
                $q->whereDate('date', '>=', $windowStart)
                    ->orWhereDate('end_date', '>=', $windowStart);
            })
            ->orderBy('date')
            ->get()
            ->map(function ($e) {
                $end = $e->end_date ?: $e->date;
                return [
                    'id' => $e->id,
                    'title' => $e->title,
                    'date' => $e->date->toDateString(),
                    'end' => $end->toDateString(),
                    'location' => $e->location,
                    'color' => $e->color ?: 'brand',
                    'multi' => (bool) $e->is_multi_day,
                ];
            })
            ->sortBy('date')->values();
    }

    /** Public holidays in the same window (name + date). */
    protected function companyHolidays()
    {
        $windowStart = today()->copy()->subDays(31)->toDateString();
        $windowEnd = today()->copy()->addDays(180)->toDateString();

        return \App\Models\Holiday::whereBetween('date', [$windowStart, $windowEnd])
            ->orderBy('date')
            ->get(['id', 'name', 'date'])
            ->map(fn ($h) => ['name' => $h->name, 'date' => \Carbon\Carbon::parse($h->date)->toDateString()])
            ->unique(fn ($h) => $h['date'] . '|' . $h['name'])
            ->values();
    }

    /**
     * Approved leaves overlapping the window — real "out of office" people per
     * day. Work From Home is excluded: they're at work, just not in the office.
     */
    protected function outOfOffice()
    {
        $windowStart = today()->copy()->subDays(31)->toDateString();
        $windowEnd = today()->copy()->addDays(180)->toDateString();

        return \App\Models\TimeOffRequest::where('status', 'approved')
            ->excludingWorkFromHome()
            ->whereDate('start_date', '<=', $windowEnd)
            ->whereDate('end_date', '>=', $windowStart)
            ->with('employee:id,first_name,last_name,avatar_url')
            ->get()
            ->filter(fn ($r) => $r->employee)
            ->map(function ($r) {
                $emp = $r->employee;
                $name = $emp->last_name ? trim($emp->last_name . ', ' . $emp->first_name) : ($emp->first_name ?: 'Employee');

                return [
                    'name' => $name,
                    'avatar' => $emp->avatar_url,
                    'initials' => $emp->initials,
                    'start' => $r->start_date->toDateString(),
                    'end' => $r->end_date->toDateString(),
                    'range' => $r->start_date->format('d M Y') . ' – ' . $r->end_date->format('d M Y'),
                    // How much leave — "2 hours" / "Half day" / "3 days" — plus the
                    // time window for an hourly request.
                    'duration' => $r->duration_label,
                    'time' => $r->time_range,
                ];
            })
            ->values();
    }

    /**
     * Birthdays, work anniversaries and new joiners across a date window, each tagged
     * with the exact date it occurs on (so the dashboard calendar can show them on
     * their own dates). Returns JSON-friendly arrays.
     */
    protected function upcomingCelebrations()
    {
        $people = \App\Models\User::where('account_status', '!=', 'deactivated')
            ->get(['id', 'first_name', 'last_name', 'avatar_url', 'date_of_birth', 'hire_date', 'joined_at']);

        // Safely parse a possibly-malformed date value; null if unparseable.
        $safeParse = function ($value) {
            if (empty($value)) {
                return null;
            }
            try {
                return \Carbon\Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        };

        // Birthdays and anniversaries RECUR every year, so we send the month-day
        // ("md") and let the calendar match it against whatever date is shown —
        // that way every birthday appears on its date no matter how far the user
        // navigates. New-joiner is a one-off, so it keeps its exact date.
        $items = collect();
        foreach ($people as $p) {
            $name = trim($p->first_name . ' ' . $p->last_name) ?: 'Employee';
            $base = ['name' => $name, 'initials' => $p->initials, 'avatar' => $p->avatar_url];

            if ($dob = $safeParse($p->date_of_birth)) {
                $items->push(array_merge($base, ['type' => 'birthday', 'md' => $dob->format('m-d'), 'label' => 'Birthday']));
            }

            if ($s = $safeParse($p->hire_date ?? $p->joined_at)) {
                $items->push(array_merge($base, ['type' => 'anniversary', 'md' => $s->format('m-d'), 'year' => (int) $s->year, 'label' => 'Work anniversary']));
                $items->push(array_merge($base, ['type' => 'new_joiner', 'date' => $s->toDateString(), 'label' => 'Joined the team']));
            }
        }

        // Probation completions — a one-off milestone on the probation end date,
        // shown only once the review is CONFIRMED (passed), never for a still-
        // pending/overdue or failed review.
        $probations = \App\Models\Probation::with('employee:id,first_name,last_name,avatar_url')
            ->where('status', 'passed')
            ->whereNotNull('end_date')
            ->get();

        foreach ($probations as $pr) {
            $emp = $pr->employee;
            if (!$emp) {
                continue;
            }
            $name = trim($emp->first_name . ' ' . $emp->last_name) ?: 'Employee';
            $items->push([
                'name' => $name,
                'initials' => $emp->initials,
                'avatar' => $emp->avatar_url,
                'type' => 'probation_completed',
                'date' => \Carbon\Carbon::parse($pr->end_date)->toDateString(),
                'label' => 'Completed probation successfully',
            ]);
        }

        return $items->values();
    }
}
