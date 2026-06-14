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

        // super_admin/hr_admin → dashboard.admin
        if ($user->isAdmin()) {
            return view('dashboard.admin');
        }

        // manager → dashboard.manager
        if ($user->isManager()) {
            return view('dashboard.manager');
        }

        // employee/restricted → dashboard.employee
        $upcomingTimeOff = \App\Models\TimeOffRequest::where('user_id', $user->id)
            ->where('start_date', '>=', today())
            ->where('status', 'approved')
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

        return view('dashboard.employee', compact('upcomingTimeOff', 'outOfOfficeCount', 'timeOffBalances', 'celebrations'));
    }

    /**
     * Birthdays, work anniversaries and new joiners across a date window, each tagged
     * with the exact date it occurs on (so the dashboard calendar can show them on
     * their own dates). Returns JSON-friendly arrays.
     */
    protected function upcomingCelebrations()
    {
        $today = today();
        $windowStart = $today->copy()->subDays(31);
        $windowEnd = $today->copy()->addDays(60);

        $people = \App\Models\User::where('account_status', '!=', 'deactivated')
            ->get(['id', 'first_name', 'last_name', 'avatar_url', 'date_of_birth', 'hire_date', 'joined_at']);

        // Find the occurrence of a month/day that falls inside the window (this year, ±1).
        $occurrence = function ($month, $day) use ($today, $windowStart, $windowEnd) {
            foreach ([$today->year - 1, $today->year, $today->year + 1] as $yr) {
                try {
                    $occ = \Carbon\Carbon::create($yr, (int) $month, (int) $day)->startOfDay();
                } catch (\Throwable $e) {
                    continue;
                }
                if ($occ->betweenIncluded($windowStart, $windowEnd)) {
                    return $occ;
                }
            }
            return null;
        };

        $items = collect();

        foreach ($people as $p) {
            $name = trim($p->first_name . ' ' . $p->last_name) ?: 'Employee';
            $base = ['name' => $name, 'initials' => $p->initials, 'avatar' => $p->avatar_url];

            if ($p->date_of_birth) {
                $dob = \Carbon\Carbon::parse($p->date_of_birth);
                if ($occ = $occurrence($dob->month, $dob->day)) {
                    $items->push(array_merge($base, ['date' => $occ->toDateString(), 'type' => 'birthday', 'label' => 'Birthday']));
                }
            }

            $start = $p->hire_date ?? $p->joined_at;
            if ($start) {
                $s = \Carbon\Carbon::parse($start);
                if ($occ = $occurrence($s->month, $s->day)) {
                    $years = $occ->year - $s->year;
                    if ($years >= 1) {
                        $items->push(array_merge($base, ['date' => $occ->toDateString(), 'type' => 'anniversary', 'label' => $years . ' year' . ($years > 1 ? 's' : '') . ' at the company']));
                    }
                }
                // New joiner — the actual join date, when it falls in the window.
                if ($s->betweenIncluded($windowStart, $windowEnd)) {
                    $items->push(array_merge($base, ['date' => $s->toDateString(), 'type' => 'new_joiner', 'label' => 'Joined the team']));
                }
            }
        }

        return $items->sortBy('date')->values();
    }
}
