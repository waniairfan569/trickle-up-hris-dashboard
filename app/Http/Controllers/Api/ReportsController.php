<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Employee;
use App\Models\TimeOffRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function dashboard(Request $request)
    {
        // Similar to DashboardController but more detail
        return response()->json(['message' => 'Reports dashboard data']);
    }

    public function hiring(Request $request)
    {
        $companyId = $request->user()->company_id;
        
        $jobs = Job::withCount([
            'candidates as applied_count' => fn($q) => $q->where('stage', 'applied'),
            'candidates as interview_count' => fn($q) => $q->where('stage', 'interview'),
            'candidates as hired_count' => fn($q) => $q->where('stage', 'hired'),
            'candidates as total_count'
        ])->where('company_id', $companyId)->get();

        return response()->json([
            'jobs' => $jobs,
            'avg_time_to_hire' => 24 // Placeholder
        ]);
    }

    public function employees(Request $request)
    {
        $companyId = $request->user()->company_id;
        
        $byDept = Employee::where('employees.company_id', $companyId)
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('count(*) as count'))
            ->groupBy('departments.name')->get();
            
        $byType = Employee::where('company_id', $companyId)
            ->select('employment_type', DB::raw('count(*) as count'))
            ->groupBy('employment_type')->get();

        return response()->json([
            'by_department' => $byDept,
            'by_type' => $byType
        ]);
    }

    public function timeOff(Request $request)
    {
        $companyId = $request->user()->company_id;
        
        $usage = TimeOffRequest::whereHas('employee', fn($q) => $q->where('company_id', $companyId))
            ->where('time_off_requests.status', 'approved')
            ->join('employees', 'time_off_requests.employee_id', '=', 'employees.id')
            ->select('employees.first_name', 'employees.last_name', 'time_off_requests.type', DB::raw('SUM(time_off_requests.days_count) as total_days'))
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'time_off_requests.type')
            ->get();

        return response()->json(['usage' => $usage]);
    }
}
