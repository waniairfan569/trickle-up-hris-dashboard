<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    /**
     * Report Center — headcount summary + employee details.
     */
    public function index(Request $request)
    {
        $auth = $request->user();
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: you do not have permission to view reports.');
        }

        $users = User::where('account_status', '!=', 'deactivated')
            ->with(['department:id,name', 'companyEntity:id,name'])
            ->get(['id', 'first_name', 'last_name', 'email', 'job_title', 'department_id', 'company_entity_id', 'account_status', 'hire_date', 'joined_at']);

        $total = $users->count();
        $active = $users->where('account_status', 'active')->count();
        $pending = $users->where('account_status', 'invited')->count();

        $recentHires = $users->filter(function ($u) {
            $start = $u->hire_date ?? $u->joined_at;
            return $start && \Carbon\Carbon::parse($start)->gte(now()->subDays(30));
        })->count();

        $byDepartment = $users->groupBy(fn ($u) => optional($u->department)->name ?: 'Unassigned')
            ->map->count()->sortDesc();

        $byEntity = $users->groupBy(fn ($u) => optional($u->companyEntity)->name ?: 'Unassigned')
            ->map->count()->sortDesc();

        $employees = $users->sortBy(fn ($u) => $u->first_name . ' ' . $u->last_name)->values();

        return view('reports.index', compact('total', 'active', 'pending', 'recentHires', 'byDepartment', 'byEntity', 'employees'));
    }
}
