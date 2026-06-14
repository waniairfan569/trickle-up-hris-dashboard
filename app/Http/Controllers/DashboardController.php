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
     * Upcoming birthdays + work anniversaries (next 30 days) and recent new joiners
     * (last 30 days), across all active employees, sorted by date.
     */
    protected function upcomingCelebrations()
    {
        $today = today();
        $windowEnd = $today->copy()->addDays(30);
        $recentStart = $today->copy()->subDays(30);

        $people = \App\Models\User::where('account_status', '!=', 'deactivated')
            ->get(['id', 'first_name', 'last_name', 'avatar_url', 'job_title', 'date_of_birth', 'hire_date', 'joined_at']);

        $items = collect();

        foreach ($people as $p) {
            // Birthday — next occurrence within 30 days
            if ($p->date_of_birth) {
                try {
                    $dob = \Carbon\Carbon::parse($p->date_of_birth);
                    $next = $today->copy()->setDate($today->year, $dob->month, $dob->day);
                    if ($next->lt($today)) {
                        $next->addYear();
                    }
                    if ($next->lte($windowEnd)) {
                        $items->push((object) ['type' => 'birthday', 'user' => $p, 'date' => $next, 'label' => 'Birthday', 'sub' => $next->format('M j')]);
                    }
                } catch (\Throwable $e) {
                }
            }

            $start = $p->hire_date ?? $p->joined_at;
            if ($start) {
                try {
                    $s = \Carbon\Carbon::parse($start);
                    // Work anniversary — completed >= 1 year, next occurrence within 30 days
                    $next = $today->copy()->setDate($today->year, $s->month, $s->day);
                    if ($next->lt($today)) {
                        $next->addYear();
                    }
                    $years = $next->year - $s->year;
                    if ($years >= 1 && $next->lte($windowEnd)) {
                        $items->push((object) ['type' => 'anniversary', 'user' => $p, 'date' => $next, 'label' => $years . ' year' . ($years > 1 ? 's' : '') . ' at the company', 'sub' => $next->format('M j')]);
                    }
                    // New joiner — joined in the last 30 days
                    if ($s->betweenIncluded($recentStart, $today)) {
                        $items->push((object) ['type' => 'new_joiner', 'user' => $p, 'date' => $s, 'label' => 'Joined the team', 'sub' => $s->format('M j')]);
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return $items->sortBy('date')->values();
    }
}
