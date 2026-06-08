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

        return view('dashboard.employee', compact('upcomingTimeOff', 'outOfOfficeCount', 'timeOffBalances'));
    }
}
