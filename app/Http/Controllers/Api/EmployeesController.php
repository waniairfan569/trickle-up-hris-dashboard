<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeesController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Employee::with(['department', 'manager'])
            ->where('company_id', $request->user()->company_id);

        if ($request->has('status')) $query->where('status', $request->input('status'));
        if ($request->has('department_id')) $query->where('department_id', $request->input('department_id'));
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 200);
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'      => 'required|string',
            'last_name'       => 'required|string',
            'email'           => 'required|email|unique:employees',
            'job_title'       => 'required|string',
            'department_id'   => 'required|exists:departments,id',
            'location_id'     => 'required|exists:locations,id',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'hire_date'       => 'required|date',
            'manager_id'      => 'nullable|exists:employees,id',
            'phone'           => 'nullable|string',
            'salary'          => 'nullable|numeric|min:0',
            'currency'        => 'nullable|string|max:10',
        ]);

        $employee = Employee::create(array_merge(
            $validated,
            ['company_id' => $request->user()->company_id]
        ));

        if ($request->input('create_user')) {
            $user = User::create([
                'company_id' => $request->user()->company_id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'password' => Hash::make(Str::random(12)),
                'role' => 'employee',
                'status' => 'active',
            ]);
            $employee->update(['user_id' => $user->id]);
        }

        $this->logActivity('created', 'Employee', $employee->id, "Added employee {$employee->email}");
        return response()->json($employee, 201);
    }

    public function show(Request $request, $id)
    {
        $employee = Employee::with(['manager', 'subordinates', 'department', 'location', 'timeOffRequests' => function($q) {
            $q->orderBy('created_at', 'desc')->take(5);
        }])->where('company_id', $request->user()->company_id)->findOrFail($id);
        
        return response()->json($employee);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::where('company_id', $request->user()->company_id)->findOrFail($id);

        $data = $request->validate([
            'first_name'       => 'sometimes|required|string',
            'last_name'        => 'sometimes|required|string',
            'email'            => 'sometimes|required|email|unique:employees,email,' . $id,
            'phone'            => 'sometimes|nullable|string',
            'job_title'        => 'sometimes|required|string',
            'employment_type'  => 'sometimes|required|in:full_time,part_time,contract',
            'status'           => 'sometimes|required|in:active,on_leave,terminated',
            'hire_date'        => 'sometimes|required|date',
            'termination_date' => 'sometimes|nullable|date',
            'department_id'    => 'sometimes|nullable|exists:departments,id',
            'location_id'      => 'sometimes|nullable|exists:locations,id',
            'manager_id'       => 'sometimes|nullable|exists:employees,id',
            'salary'           => 'sometimes|nullable|numeric|min:0',
            'currency'         => 'sometimes|nullable|string|max:10',
        ]);

        $employee->update($data);

        $this->logActivity('updated', 'Employee', $employee->id, "Updated employee {$employee->email}");
        return response()->json($employee->fresh(['department', 'location', 'manager', 'subordinates']));
    }

    public function timeOff(Request $request, $id)
    {
        $employee = Employee::where('company_id', $request->user()->company_id)->findOrFail($id);
        return response()->json($employee->timeOffRequests()->paginate(15));
    }
}
