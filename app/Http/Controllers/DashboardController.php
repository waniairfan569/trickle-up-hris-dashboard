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

        // Shared widgets shown on every dashboard (calendar + time-off balances).
        // Show anything still on the calendar: approved leave AND requests still
        // awaiting a decision (pending). Rejected/cancelled aren't "upcoming".
        $upcomingTimeOff = \App\Models\TimeOffRequest::where('user_id', $user->id)
            ->whereDate('end_date', '>=', today())
            ->whereIn('status', ['approved', 'pending'])
            ->orderBy('start_date', 'asc')
            ->get();

        $outOfOfficeCount = \App\Models\TimeOffRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();

        $timeOffBalances = \App\Models\TimeOffBalance::where('user_id', $user->id)
            ->where('year', date('Y'))
            ->with('policy')
            ->get();

        $celebrations = $this->upcomingCelebrations();
        $events = $this->companyEvents();
        $holidays = $this->companyHolidays();
        $outOfOffice = $this->outOfOffice();
        $onLeavePeople = \App\Models\TimeOffRequest::onLeaveToday();

        $shared = compact('upcomingTimeOff', 'outOfOfficeCount', 'outOfOffice', 'timeOffBalances', 'celebrations', 'events', 'holidays', 'onLeavePeople');

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
     * Active company events in a window — ONE entry per event carrying its
     * start and end dates. A multi-day event is a single ranged row (the client
     * renders "28 Jul – 15 Aug"), not one row per day it covers.
     */
    protected function companyEvents()
    {
        $windowStart = today()->copy()->subDays(31);
        $windowEnd = today()->copy()->addDays(180);

        return \App\Models\Event::active()
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

    /** Approved leaves overlapping the window — real "out of office" people per day. */
    protected function outOfOffice()
    {
        $windowStart = today()->copy()->subDays(31)->toDateString();
        $windowEnd = today()->copy()->addDays(180)->toDateString();

        return \App\Models\TimeOffRequest::where('status', 'approved')
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
