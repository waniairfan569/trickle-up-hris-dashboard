<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employee;
use App\Services\EmployeeAccessService;
use App\Services\HRPermissionService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    protected EmployeeAccessService $employeeAccessService;
    protected HRPermissionService $hrPermissionService;

    /**
     * Constructor injecting access and permission services.
     */
    public function __construct(
        EmployeeAccessService $employeeAccessService,
        HRPermissionService $hrPermissionService
    ) {
        $this->employeeAccessService = $employeeAccessService;
        $this->hrPermissionService = $hrPermissionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $auth = $request->user();

        if (!$auth) {
            abort(401, 'Unauthenticated.');
        }

        $query = $this->employeeAccessService->getScopedEmployeeQuery($auth)
            ->with(['user', 'department', 'user.roles']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($q2) use ($search) {
                    $q2->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->whereHas('department', function($q) use ($request) {
                $q->where('name', $request->department);
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('user.roles', function($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        $employees = $query->paginate(10)->withQueryString();

        // Pass all departments and roles for the filter dropdowns (ignoring scope for dropdown options for simplicity)
        $departments = \App\Models\Department::pluck('name');
        $rolesMap = \App\Models\Role::pluck('name', 'slug');

        return view('employees.index', compact('employees', 'departments', 'rolesMap'));
    }

    /**
     * Display a listing of pending invitations.
     */
    public function pendingInvitations(Request $request)
    {
        $auth = $request->user();

        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: You do not have permission to view pending invitations.');
        }

        $pendingUsers = User::with(['department', 'invitedBy', 'roles'])
            ->whereNull('invitation_accepted_at')
            ->where(function($query) {
                $query->where('account_status', 'invited')
                      ->orWhere(function($subQuery) {
                          $subQuery->where('account_status', 'active')
                                   ->where('must_change_password', true);
                      });
            })
            ->get();

        return view('employees.pending-invitations', compact('pendingUsers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $auth = $request->user();

        if (!$auth || !$auth->hasPermission('create_employee')) {
            abort(403, 'Forbidden: You do not have permission to create employees.');
        }

        $departments = \App\Models\Department::all();
        $locations = \App\Models\Location::all();
        $roles = \App\Models\Role::all();
        $templates = \App\Models\ProfileTemplate::with('sections.fields')->active()->get();

        return view('employees.create', compact('departments', 'locations', 'roles', 'templates'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $auth = $request->user();

        if (!$auth || !$auth->hasPermission('create_employee')) {
            abort(403, 'Forbidden: You do not have permission to create employees.');
        }

        $validated = $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email|unique:employees,email',
            'job_title'     => 'nullable|string|max:255',
            'salary'        => 'nullable|numeric',
            'role_id'       => 'required|exists:roles,id',
            'department_id' => 'required|exists:departments,id',
            'location_id'   => 'required|exists:locations,id',
            'dynamic_fields'=> 'nullable|array',
            'onboarding_method' => 'required|in:invite,set_password,later',
            'temporary_password' => 'required_if:onboarding_method,set_password',
        ]);

        $user = User::create([
            'company_id'    => $auth->company_id,
            'first_name'    => $validated['first_name'],
            'last_name'     => $validated['last_name'],
            'email'         => $validated['email'],
            'password'      => bcrypt(\Illuminate\Support\Str::random(16)),
            'job_title'     => $validated['job_title'] ?? 'Employee',
            'department_id' => $validated['department_id'],
            'salary'        => $validated['salary'] ?? null,
            'status'        => 'active',
            'account_status'  => 'invited', // Added for invitation logic
            'employee_status' => 'active',
            'joined_at'     => now(),
        ]);

        // Assign Role
        $role = \App\Models\Role::find($validated['role_id']);
        if ($role) {
            $user->roles()->attach($role->id, [
                'assigned_by' => $auth->id,
                'assigned_at' => now(),
            ]);
        }

        $employee = Employee::create([
            'user_id'         => $user->id,
            'company_id'      => $user->company_id,
            'department_id'   => $validated['department_id'],
            'location_id'     => $validated['location_id'],
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'email'           => $user->email,
            'job_title'       => $user->job_title,
            'employment_type' => 'full_time',
            'hire_date'       => now(),
            'status'          => 'active',
            'salary'          => $user->salary,
        ]);

        // Attach Templates and Save Dynamic Fields
        $templates = \App\Models\ProfileTemplate::active()->get();
        foreach ($templates as $template) {
            $user->profileTemplates()->attach($template->id, [
                'assigned_by' => $auth->id,
                'assigned_at' => now(),
            ]);
        }

        if (!empty($validated['dynamic_fields'])) {
            foreach ($validated['dynamic_fields'] as $fieldId => $value) {
                if (!is_null($value)) {
                    // For array types like multi_select
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    \App\Models\EmployeeFieldValue::create([
                        'user_id'    => $user->id,
                        'field_id'   => $fieldId,
                        'value'      => (string) $value,
                        'updated_by' => $auth->id,
                    ]);
                }
            }
        }

        $invitationService = app(\App\Services\InvitationService::class);
        
        if ($validated['onboarding_method'] === 'invite') {
            $invitationService->sendInvitation($user, $auth);
        } elseif ($validated['onboarding_method'] === 'set_password') {
            $invitationService->setPasswordNow($user, $validated['temporary_password'], $auth);
        }

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $employee)
    {
        $auth = $request->user();

        if (!$auth) {
            abort(401, 'Unauthenticated.');
        }

        // Double-layer security check (middleware + controller layer check)
        if (!$auth->canView($employee)) {
            abort(403, 'Forbidden: You do not have access to this employee profile.');
        }

        $fields = $this->filterEmployeeFields($auth, $employee);

        return view('employees.show', compact('employee', 'fields'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $employee)
    {
        $auth = $request->user();

        if (!$auth) {
            abort(401, 'Unauthenticated.');
        }

        if (!$auth->canEdit($employee)) {
            abort(403, 'Forbidden: You do not have permission to edit this employee profile.');
        }

        $fields = $this->filterEmployeeFields($auth, $employee);

        return view('employees.edit', compact('employee', 'fields'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $employee)
    {
        $auth = $request->user();

        if (!$auth) {
            abort(401, 'Unauthenticated.');
        }

        if (!$auth->canEdit($employee)) {
            abort(403, 'Forbidden: You do not have permission to edit this employee profile.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $employee->id . '|unique:employees,email,user_id,' . $employee->id,
            'phone'      => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:255',
            'job_title'  => 'nullable|string|max:255',
            'salary'     => 'nullable|numeric',
        ]);

        $userData = [
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
        ];

        // Contact info: self + admin only
        if ($auth->id === $employee->id || $auth->isAdmin()) {
            if (isset($validated['phone'])) {
                $userData['phone'] = $validated['phone'];
            }
            if (isset($validated['address'])) {
                $userData['address'] = $validated['address'];
            }
        }

        // Salary, Job Title: admin only
        if ($auth->isAdmin()) {
            if (isset($validated['job_title'])) {
                $userData['job_title'] = $validated['job_title'];
            }
            if (isset($validated['salary'])) {
                $userData['salary'] = $validated['salary'];
            }
        }

        $employee->update($userData);

        // Keep Employee model synced if it exists
        $emp = Employee::where('user_id', $employee->id)->first();
        if ($emp) {
            $empData = [
                'first_name' => $employee->first_name,
                'last_name'  => $employee->last_name,
                'email'      => $employee->email,
                'phone'      => $employee->phone,
            ];
            if ($auth->isAdmin()) {
                if (isset($employee->job_title)) {
                    $empData['job_title'] = $employee->job_title;
                }
                if (isset($employee->salary)) {
                    $empData['salary'] = $employee->salary;
                }
            }
            $emp->update($empData);
        }

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    /**
     * Filter user profile fields based on the authenticated user's access permissions.
     */
    protected function filterEmployeeFields(User $auth, User $employee): array
    {
        // basic profile: visible to all who have access
        $fields = [
            'id'              => $employee->id,
            'first_name'      => $employee->first_name,
            'last_name'       => $employee->last_name,
            'initials'        => $employee->initials,
            'job_title'       => $employee->job_title,
            'employee_status' => $employee->employee_status,
            'joined_at'       => $employee->joined_at,
            'department_id'   => $employee->department_id,
            'manager_id'      => $employee->manager_id,
        ];

        // contact info: self + admin
        if ($auth->id === $employee->id || $auth->isAdmin()) {
            $fields['email'] = $employee->email;
            $fields['phone'] = $employee->phone;
            $fields['address'] = $employee->address;
            $fields['city'] = $employee->city;
            $fields['country'] = $employee->country;
        }

        // salary, performance_notes: super_admin, hr_admin only
        if ($auth->isAdmin()) {
            $fields['salary']            = $employee->salary;
            $fields['performance_notes'] = $employee->admin_notes ?? '';
        }

        return $fields;
    }

    public function exportCsv()
    {
        $employees = User::with('department')->get();
        $filename = "employees_export_" . date('Y_m_d_H_i_s') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'First Name', 'Last Name', 'Email', 'Job Title', 'Employee ID', 'Department', 'Hire Date',
            'Phone', 'Address', 'City', 'Country', 'Timezone', 'Date of Birth', 'Nationality', 'Gender',
            'Languages', 'LinkedIn URL', 'GitHub URL', 'Portfolio URL', 'Employee Status', 'Joined At',
            'Contract Type', 'Salary', 'Salary Currency', 'Notice Period Days', 'Years of Experience',
            'Education', 'Specialization', 'Admin Notes'
        ];

        $callback = function() use($employees, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($employees as $emp) {
                fputcsv($file, [
                    $emp->first_name, $emp->last_name, $emp->email, $emp->job_title, $emp->employee_id,
                    $emp->department ? $emp->department->name : '', $emp->hire_date,
                    $emp->phone, $emp->address, $emp->city, $emp->country, $emp->timezone,
                    $emp->date_of_birth, $emp->nationality, $emp->gender, $emp->languages,
                    $emp->linkedin_url, $emp->github_url, $emp->portfolio_url, $emp->employee_status, $emp->joined_at,
                    $emp->contract_type, $emp->salary, $emp->salary_currency, $emp->notice_period_days, $emp->years_of_experience,
                    $emp->education, $emp->specialization, $emp->admin_notes
                ]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function importCsv(\Illuminate\Http\Request $request)
    {
        $request->validate(['import_file' => 'required|file|mimes:csv,txt|max:2048']);
        $file = $request->file('import_file');
        $handle = fopen($file->getRealPath(), "r");
        
        // Read header
        $header = fgetcsv($handle, 10000, ",");
        if (!$header) {
            return back()->with('error', 'Invalid CSV file format.');
        }

        // Map column names to indexes to allow flexible column ordering
        $colMap = array_flip(array_map('strtolower', array_map('trim', $header)));
        
        $entity = \App\Models\CompanyEntity::primary() ?? \App\Models\CompanyEntity::first();
        $role = \App\Models\Role::where('slug', 'employee')->first();
        
        $count = 0;
        while ($csvLine = fgetcsv($handle, 10000, ",")) {
            $getValue = function($colName) use ($csvLine, $colMap) {
                $idx = $colMap[strtolower($colName)] ?? null;
                return $idx !== null ? ($csvLine[$idx] ?? null) : null;
            };

            $email = $getValue('email');
            if (empty($email)) continue;

            $departmentName = $getValue('department');
            $departmentId = null;
            if (!empty($departmentName)) {
                $dept = \App\Models\Department::firstOrCreate(['name' => trim($departmentName)]);
                $departmentId = $dept->id;
            }

            $data = [
                'first_name' => $getValue('first name'),
                'last_name' => $getValue('last name'),
                'job_title' => $getValue('job title'),
                'employee_id' => $getValue('employee id'),
                'department_id' => $departmentId,
                'hire_date' => $getValue('hire date') ?: null,
                'phone' => $getValue('phone'),
                'address' => $getValue('address'),
                'city' => $getValue('city'),
                'country' => $getValue('country'),
                'timezone' => $getValue('timezone'),
                'date_of_birth' => $getValue('date of birth') ?: null,
                'nationality' => $getValue('nationality'),
                'gender' => $getValue('gender'),
                'languages' => $getValue('languages'),
                'linkedin_url' => $getValue('linkedin url'),
                'github_url' => $getValue('github url'),
                'portfolio_url' => $getValue('portfolio url'),
                'employee_status' => $getValue('employee status'),
                'joined_at' => $getValue('joined at') ?: null,
                'contract_type' => $getValue('contract type'),
                'salary' => $getValue('salary') ?: null,
                'salary_currency' => $getValue('salary currency'),
                'notice_period_days' => $getValue('notice period days') ?: null,
                'years_of_experience' => $getValue('years of experience') ?: null,
                'education' => $getValue('education'),
                'specialization' => $getValue('specialization'),
                'admin_notes' => $getValue('admin notes'),
            ];

            // Clean up nulls so we don't overwrite existing data with nulls if blank
            $data = array_filter($data, function($v) { return $v !== null && $v !== ''; });

            $user = User::where('email', trim($email))->first();

            if ($user) {
                $user->update($data);
            } else {
                $data['company_id'] = $entity ? $entity->id : 1;
                $data['email'] = trim($email);
                $data['password'] = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16));
                
                if (empty($data['status'])) $data['status'] = 'active';
                if (empty($data['employee_status'])) $data['employee_status'] = 'active';
                
                $data['account_status'] = 'invited';
                $data['must_change_password'] = true;

                $user = User::create($data);

                if ($role) {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }
            }
            $count++;
        }
        
        fclose($handle);

        return back()->with('success', "Successfully imported {$count} employees with full profile data.");
    }
}
