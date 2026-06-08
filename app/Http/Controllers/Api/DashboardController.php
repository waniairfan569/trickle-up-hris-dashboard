<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\TimeOffRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return response()->json([
            'total_employees' => [
                'active' => Employee::where('company_id', $companyId)->where('status', 'active')->count(),
                'total'  => Employee::where('company_id', $companyId)->count(),
            ],

            'open_time_off_requests' => TimeOffRequest::whereHas('employee', fn($q) => $q->where('company_id', $companyId))
                ->where('status', 'pending')->count(),

            'users_by_role' => User::where('users.company_id', $companyId)
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->select('roles.name as role', DB::raw('count(*) as count'))
                ->groupBy('roles.name')->get(),

            'recent_hires' => Employee::with('department')
                ->where('company_id', $companyId)
                ->orderBy('hire_date', 'desc')->take(5)->get(),

            'pending_time_off' => TimeOffRequest::with('employee')
                ->whereHas('employee', fn($q) => $q->where('company_id', $companyId))
                ->where('status', 'pending')->orderBy('created_at', 'desc')->take(3)->get(),

            'recent_activity' => ActivityLog::with('user')
                ->where('company_id', $companyId)
                ->orderBy('created_at', 'desc')->take(8)->get(),

            'time_off_summary' => [
                'pending'        => TimeOffRequest::where('status', 'pending')->whereNull('revoked_at')->count(),
                'approved_today' => TimeOffRequest::where('status', 'approved')->whereDate('updated_at', today())->count(),
                'revoked_total'  => TimeOffRequest::whereNotNull('revoked_at')->count(),
                'admin_created'  => TimeOffRequest::where('is_admin_created', true)->count(),
                'on_leave_today' => TimeOffRequest::where('status', 'approved')
                    ->where('start_date', '<=', today())
                    ->where('end_date', '>=', today())
                    ->whereNull('revoked_at')->count(),
                'suspicious_count' => Employee::with(['timeOffRequests' => function ($q) {
                    $q->where('status', 'approved')->whereNull('revoked_at');
                }])->get()->filter(function ($emp) {
                    $used        = $emp->timeOffRequests->where('type', 'annual')->sum('days_count');
                    $entitlement = $emp->annual_leave_days ?? 20;
                    return $used > ($entitlement * 0.8);
                })->count()
                + TimeOffRequest::where('days_count', '>', 10)->where('status', '!=', 'cancelled')->whereNull('revoked_at')->count()
                + TimeOffRequest::whereNotNull('revoked_at')->count(),
            ],
        ]);
    }
}
