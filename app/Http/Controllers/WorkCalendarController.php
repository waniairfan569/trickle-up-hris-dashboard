<?php

namespace App\Http\Controllers;

use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WorkCalendarController extends Controller
{
    /**
     * Company-wide weekly work calendar: every employee's shift per day.
     */
    public function index(Request $request)
    {
        $auth = $request->user();
        if (!$auth) {
            abort(401, 'Unauthenticated.');
        }

        $start = $request->filled('week')
            ? Carbon::parse($request->week)->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6);
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));

        $usersQuery = User::where('account_status', '!=', 'deactivated')
            ->with('department:id,name');
        if ($request->filled('department')) {
            $usersQuery->whereHas('department', fn ($q) => $q->where('name', $request->department));
        }
        $users = $usersQuery->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'avatar_url', 'job_title', 'department_id']);

        $assignments = ShiftAssignment::with('shift')
            ->whereIn('user_id', $users->pluck('id'))
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($r) use ($start, $end) {
                    $r->where('assignment_type', 'recurring')
                      ->where(fn ($s) => $s->whereNull('recurring_start_date')->orWhere('recurring_start_date', '<=', $end->toDateString()))
                      ->where(fn ($s) => $s->whereNull('recurring_end_date')->orWhere('recurring_end_date', '>=', $start->toDateString()));
                })->orWhere(function ($r) use ($start, $end) {
                    $r->where('assignment_type', 'single')
                      ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
                });
            })
            ->get()
            ->groupBy('user_id');

        $d = fn ($v) => $v ? Carbon::parse($v)->toDateString() : null;

        // Resolve [user_id][Y-m-d] => shift (single overrides recurring).
        $schedule = [];
        foreach ($users as $u) {
            $ua = $assignments[$u->id] ?? collect();
            foreach ($days as $day) {
                $ds = $day->toDateString();
                $dow = $day->format('D');

                $single = $ua->first(fn ($a) => $a->assignment_type === 'single' && $d($a->date) === $ds);
                if ($single) {
                    $schedule[$u->id][$ds] = $single->shift;
                    continue;
                }

                $rec = $ua->first(function ($a) use ($d, $ds, $dow) {
                    if ($a->assignment_type !== 'recurring') {
                        return false;
                    }
                    $startOk = !$a->recurring_start_date || $d($a->recurring_start_date) <= $ds;
                    $endOk = !$a->recurring_end_date || $d($a->recurring_end_date) >= $ds;
                    return $startOk && $endOk && in_array($dow, (array) ($a->recurring_days ?: []));
                });
                $schedule[$u->id][$ds] = $rec ? $rec->shift : null;
            }
        }

        $departments = \App\Models\Department::orderBy('name')->pluck('name');

        return view('work-calendar.index', compact('users', 'days', 'schedule', 'start', 'departments'));
    }
}
