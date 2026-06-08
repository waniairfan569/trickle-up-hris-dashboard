<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Department, Location};
use Illuminate\Http\Request;

class CommonController extends Controller
{
    public function departments(Request $request)
    {
        return response()->json(Department::orderBy('name')->get());
    }

    public function departmentsOrg(Request $request)
    {
        $companyId = $request->user()->company_id;

        $empQuery = function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->where('status', '!=', 'terminated')
              ->with('manager:id,first_name,last_name,job_title');
        };

        $departments = Department::where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->with([
                'employees'                    => $empQuery,
                'children'                     => function ($q) use ($companyId) {
                    $q->where(function ($q2) use ($companyId) {
                        $q2->where('company_id', $companyId)->orWhereNull('company_id');
                    });
                },
                'children.employees'           => $empQuery,
                'children.children'            => function ($q) use ($companyId) {
                    $q->where(function ($q2) use ($companyId) {
                        $q2->where('company_id', $companyId)->orWhereNull('company_id');
                    });
                },
                'children.children.employees'  => $empQuery,
            ])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function createDepartment(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:departments,id',
        ]);

        $dept = Department::create(array_merge($data, [
            'company_id' => $request->user()->company_id,
        ]));

        return response()->json($dept, 201);
    }

    public function locations(Request $request)
    {
        return response()->json(Location::orderBy('name')->get());
    }
}
