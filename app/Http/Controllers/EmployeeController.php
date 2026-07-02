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
            ->with(['user', 'department', 'user.roles', 'user.jobLocation', 'user.manager'])
            // Hide archived (deactivated) employees from the main directory. Non-admins
            // only ever see ACTIVE colleagues; admins also see invited/pending/suspended.
            ->whereHas('user', function ($q) use ($auth) {
                if ($auth->isAdmin()) {
                    $q->where('account_status', '!=', 'deactivated');
                } else {
                    $q->where('account_status', 'active');
                }
            });

        if ($request->filled('job_location_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('job_location_id', $request->job_location_id);
            });
        }

        if ($request->filled('company_entity_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('company_entity_id', $request->company_entity_id);
            });
        }

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

        // Sorting (directory "Sort By" dropdown)
        switch ($request->get('sort', 'name_asc')) {
            case 'name_desc':
                $query->orderBy('last_name', 'desc')->orderBy('first_name', 'desc');
                break;
            case 'job_title':
                $query->orderBy('job_title')->orderBy('last_name');
                break;
            case 'department':
                $query->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
                      ->orderBy('departments.name')
                      ->select('employees.*');
                break;
            case 'recent':
                $query->orderByDesc('employees.created_at');
                break;
            case 'name_asc':
            default:
                // Alphabetical by last name, then first name (A→Z).
                $query->orderBy('last_name')->orderBy('first_name');
                break;
        }

        $employees = $query->paginate(10)->withQueryString();

        // Pass all departments and roles for the filter dropdowns (ignoring scope for dropdown options for simplicity)
        $departments = \App\Models\Department::pluck('name');
        $rolesMap = \App\Models\Role::pluck('name', 'slug');
        $entities = \App\Models\CompanyEntity::orderBy('name')->get(['id', 'name']);
        $jobLocations = \App\Models\JobLocation::orderBy('name')->get(['id', 'name']);

        return view('employees.index', compact('employees', 'departments', 'rolesMap', 'entities', 'jobLocations'));
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
            // Archived (deactivated) accounts are never "pending".
            ->where('account_status', '!=', 'deactivated')
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
     * Visual org chart — reporting hierarchy built from manager_id.
     */
    public function orgChart(Request $request)
    {
        $auth = $request->user();
        if (!$auth) {
            abort(401, 'Unauthenticated.');
        }

        $users = \App\Models\User::where('account_status', '!=', 'deactivated')
            ->with('department:id,name')
            ->get(['id', 'first_name', 'last_name', 'avatar_url', 'job_title', 'department_id', 'manager_id', 'city', 'country']);

        $ids = $users->pluck('id')->all();
        $byManager = $users->groupBy('manager_id');

        // Build a nested tree (id, name, title, dept, location, avatar, initials, children).
        $build = function ($u) use (&$build, $byManager) {
            $children = $byManager->get($u->id, collect());

            return [
                'id' => $u->id,
                'name' => $u->last_name ? trim($u->last_name . ', ' . $u->first_name) : ($u->first_name ?: 'Employee'),
                'title' => $u->job_title,
                'dept' => $u->department?->name,
                'location' => trim(implode(', ', array_filter([$u->city, $u->country]))) ?: null,
                'avatar' => $u->avatar_url,
                'initials' => $u->initials,
                'count' => $children->count(),
                'children' => $children->sortBy('last_name')->map($build)->values()->all(),
            ];
        };

        // Roots: no manager, or a manager who isn't in the active set.
        $tree = $users->filter(fn ($u) => empty($u->manager_id) || !in_array($u->manager_id, $ids))
            ->sortBy('last_name')
            ->map($build)
            ->values()
            ->all();

        return view('org-chart.index', compact('tree'));
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
        $jobLocations = \App\Models\JobLocation::active()->orderBy('name')->get();
        $roles = \App\Models\Role::all();
        // The create form mirrors the DEFAULT employee profile template (the single
        // source of truth). Dynamic templates are assigned later, per employee.
        $templates = \App\Models\ProfileTemplate::with('sections.fields')
            ->active()->where('type', 'default')->get();

        return view('employees.create', compact('departments', 'locations', 'jobLocations', 'roles', 'templates'));
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

        // Required to create the account: name + work email (login) + personal email.
        // Everything else on the default profile template is optional and can be
        // completed later by the employee, so the template stays the single source of truth.
        $validated = $request->validate([
            'first_name'            => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'fields.work_email'     => 'required|email|unique:users,email|unique:employees,email',
            'fields.personal_email' => 'required|email',
            'fields.department_id'  => 'nullable|exists:departments,id',
            'fields.manager_id'     => 'nullable|exists:users,id',
            'role_id'               => 'nullable|exists:roles,id',
            'onboarding_method'     => 'required|in:invite,set_password,later',
            'temporary_password'    => 'required_if:onboarding_method,set_password',
            'fields'                => 'nullable|array',
        ], [], [
            'fields.work_email'     => 'work email',
            'fields.personal_email' => 'personal email',
            'fields.department_id'  => 'department',
            'fields.manager_id'     => 'manager',
        ]);

        $email    = trim($request->input('fields.work_email'));
        $jobTitle = $request->input('fields.job_title') ?: 'Employee';

        $user = User::create([
            'company_id'        => $auth->company_id,
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'],
            'email'             => $email,
            'password'          => bcrypt(\Illuminate\Support\Str::random(16)),
            'job_title'         => $jobTitle,
            'company_entity_id' => optional(\App\Models\CompanyEntity::primary())->id,
            'timezone'          => 'UTC',
            'salary_currency'   => 'PKR',
            'status'            => 'active',
            'account_status'    => 'invited',
            'employee_status'   => 'active',
            'joined_at'         => now(),
        ]);

        // Assign Role — default to the standard "employee" role when none chosen.
        $role = !empty($validated['role_id'])
            ? \App\Models\Role::find($validated['role_id'])
            : \App\Models\Role::where('slug', 'employee')->first();
        if ($role) {
            $user->roles()->attach($role->id, ['assigned_by' => $auth->id, 'assigned_at' => now()]);
        }

        // Persist every submitted template field — native column when the key is a
        // fillable attribute, otherwise a dynamic EmployeeFieldValue. Same rules as the
        // profile editor (EmployeeProfileController::update) so create/view/edit agree.
        foreach ($request->input('fields', []) as $key => $value) {
            $field = \App\Models\ProfileField::where('key', $key)->first();
            if (!$field) {
                continue;
            }
            $hasFile = $request->hasFile("fields.{$key}");
            if (!$hasFile && (is_null($value) || $value === '' || (is_array($value) && count($value) === 0))) {
                continue;
            }
            if ($field->type === 'file' && $hasFile) {
                $path = $request->file("fields.{$key}")->store("employee-files/{$user->id}/{$key}", 'public');
                $value = \Illuminate\Support\Facades\Storage::url($path);
            } elseif (in_array($field->type, ['multi_select', 'date_range']) && is_array($value)) {
                $value = json_encode($value);
            }
            try {
                if ($user->isFillable($key) || array_key_exists($key, $user->getAttributes())) {
                    $user->update([$key => $value]);
                } else {
                    \App\Models\EmployeeFieldValue::updateOrCreate(
                        ['user_id' => $user->id, 'field_id' => $field->id],
                        ['value' => $value, 'updated_by' => $auth->id]
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Create employee: field '{$key}' skipped — " . $e->getMessage());
            }
        }

        // Keep the template's "Full name" field in sync with first/last name.
        if ($fullNameField = \App\Models\ProfileField::where('key', 'full_name')->first()) {
            \App\Models\EmployeeFieldValue::updateOrCreate(
                ['user_id' => $user->id, 'field_id' => $fullNameField->id],
                ['value' => trim($user->first_name . ' ' . $user->last_name), 'updated_by' => $auth->id]
            );
        }

        // Keep the job location's cached employee count in sync (if one was set via a field).
        if ($user->job_location_id) {
            optional(\App\Models\JobLocation::find($user->job_location_id))->refreshEmployeeCount();
        }

        // Mirror into the legacy employees table so the new hire shows in the directory.
        $employee = Employee::create([
            'user_id'         => $user->id,
            'company_id'      => $user->company_id,
            'department_id'   => $user->department_id,
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'email'           => $user->email,
            'job_title'       => $user->job_title ?: 'Employee',
            'employment_type' => 'full_time',
            'hire_date'       => $user->hire_date ?? now(),
            'status'          => 'active',
            'salary'          => $user->salary,
            'currency'        => $user->salary_currency ?? 'PKR',
            'phone'           => $user->phone,
        ]);

        $invitationService = app(\App\Services\InvitationService::class);
        $method = $validated['onboarding_method'] ?? 'invite';

        // The employee is already saved at this point. Don't let a mail/onboarding
        // failure (e.g. SMTP not configured) bubble up as a 500 — the record must stick.
        try {
            if ($method === 'invite') {
                $invitationService->sendInvitation($user, $auth);
            } elseif ($method === 'set_password') {
                $invitationService->setPasswordNow($user, $validated['temporary_password'], $auth);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Employee {$user->email} created but onboarding step failed: " . $e->getMessage());

            return redirect()->route('employees.index')
                ->with('success', "Employee {$user->first_name} {$user->last_name} created. The invitation email couldn't be sent (check mail settings) — you can resend it from the employee list.");
        }

        return redirect()->route('employees.index')
            ->with('success', "Employee {$user->first_name} {$user->last_name} created successfully.");
    }

    /**
     * Archive (deactivate) an employee — reversible. Keeps all records; revokes access.
     */
    public function deactivate(Request $request, User $employee)
    {
        $auth = $request->user();
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: you do not have permission to deactivate employees.');
        }
        if ($auth->id === $employee->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($employee->hasRole('super_admin')
            && User::whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))->count() <= 1) {
            return back()->with('error', 'You cannot deactivate the last Super Admin.');
        }

        $employee->account_status = 'deactivated';
        $employee->save();

        return back()->with('success', "{$employee->full_name} has been deactivated and moved to Archived.");
    }

    /**
     * Restore an archived (deactivated) employee back into the directory.
     */
    public function restore(Request $request, User $employee)
    {
        $auth = $request->user();
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: you do not have permission to restore employees.');
        }

        // Back to active if they had already accepted their invite, otherwise pending.
        $employee->account_status = $employee->invitation_accepted_at ? 'active' : 'invited';
        $employee->save();

        return back()->with('success', "{$employee->full_name} has been restored.");
    }

    /**
     * List archived (deactivated) employees.
     */
    public function archived(Request $request)
    {
        $auth = $request->user();
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden.');
        }

        $employees = Employee::with(['user', 'department'])
            ->whereHas('user', fn ($q) => $q->where('account_status', 'deactivated'))
            ->paginate(15);

        return view('employees.archived', compact('employees'));
    }

    /**
     * Permanently delete an employee (and their dependent records). Use from Archived.
     */
    public function destroy(Request $request, User $employee)
    {
        $auth = $request->user();

        // Only Super Admin / HR Admin may delete employees.
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: you do not have permission to delete employees.');
        }

        // Safety guards: never delete yourself or the last Super Admin (avoids lockout).
        if ($auth->id === $employee->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($employee->hasRole('super_admin')
            && User::whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))->count() <= 1) {
            return back()->with('error', 'You cannot delete the last Super Admin.');
        }

        $name = $employee->full_name;
        $jobLocationId = $employee->job_location_id;

        \Illuminate\Support\Facades\DB::transaction(function () use ($employee) {
            // employees.user_id is SET NULL on delete, so remove the directory mirror
            // explicitly. Every other dependent (field values, attendance, leave,
            // role/policy pivots, …) is removed by ON DELETE CASCADE.
            Employee::where('user_id', $employee->id)->delete();
            $employee->delete();
        });

        if ($jobLocationId) {
            optional(\App\Models\JobLocation::find($jobLocationId))->refreshEmployeeCount();
        }

        return redirect()->route('employees.archived')->with('success', "{$name} has been permanently deleted.");
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
    /** Change a user's RBAC role (super admin only). */
    public function updateRole(Request $request, User $employee)
    {
        $auth = $request->user();
        abort_unless($auth && $auth->hasRole(\App\Models\Role::SUPER_ADMIN), 403, 'Only a super admin can change roles.');

        if ($employee->id === $auth->id) {
            return back()->withErrors(['role' => "You can't change your own role."]);
        }

        $validated = $request->validate([
            'role' => ['required', \Illuminate\Validation\Rule::in([
                \App\Models\Role::SUPER_ADMIN,
                \App\Models\Role::HR_ADMIN,
                \App\Models\Role::MANAGER,
                \App\Models\Role::EMPLOYEE,
                \App\Models\Role::RESTRICTED,
            ])],
        ]);

        $role = \App\Models\Role::where('slug', $validated['role'])->firstOrFail();

        // Replace all existing roles with the chosen one.
        $employee->roles()->sync([
            $role->id => ['assigned_by' => $auth->id, 'assigned_at' => now()],
        ]);

        return back()->with('success', $employee->full_name . "'s role updated to {$role->name}.");
    }

    /**
     * Set an employee's attendance mode: biometric (device only — dashboard
     * clock-in hidden) or remote (dashboard clock-in enabled). Admins only.
     */
    public function updateAttendanceMode(Request $request, User $employee)
    {
        $auth = $request->user();
        abort_unless($auth && $auth->isAdmin(), 403, 'Only an admin can change attendance mode.');

        $validated = $request->validate([
            'attendance_mode' => ['required', \Illuminate\Validation\Rule::in(['biometric', 'remote'])],
        ]);

        $employee->update(['attendance_mode' => $validated['attendance_mode']]);

        $label = $validated['attendance_mode'] === 'remote' ? 'Remote (dashboard clock-in)' : 'On-site (biometric device)';
        return back()->with('success', $employee->full_name . "'s attendance mode set to {$label}.");
    }

    /** Bulk "Attendance Mode" page: pick many employees and set them at once. */
    public function attendanceMode(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $query = User::where('account_status', '!=', 'deactivated');

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if (in_array($request->get('mode'), ['biometric', 'remote'], true)) {
            $query->where('attendance_mode', $request->get('mode'));
        }

        $employees = $query->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'attendance_mode', 'zkteco_uid']);

        return view('employees.attendance-mode', compact('employees'));
    }

    /** Apply an attendance mode to every selected employee. Admins only. */
    public function bulkAttendanceMode(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'attendance_mode' => ['required', \Illuminate\Validation\Rule::in(['biometric', 'remote'])],
        ]);

        $count = User::whereIn('id', $validated['user_ids'])
            ->update(['attendance_mode' => $validated['attendance_mode']]);

        $label = $validated['attendance_mode'] === 'remote' ? 'Remote · Dashboard' : 'On-site · Biometric';
        return back()->with('success', "{$count} employee(s) set to {$label}.");
    }

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
        $employees = User::with(['department', 'companyEntity', 'jobLocation'])->get();
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
            'Education', 'Specialization', 'Admin Notes', 'Entity', 'Job Location'
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
                    $emp->education, $emp->specialization, $emp->admin_notes,
                    optional($emp->companyEntity)->name, optional($emp->jobLocation)->name
                ]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function importCsv(\Illuminate\Http\Request $request)
    {
        $request->validate(['import_file' => 'required|file|mimes:csv,txt,tsv|max:5120']);
        $file = $request->file('import_file');
        $handle = fopen($file->getRealPath(), "r");
        if (!$handle) {
            return back()->with('error', 'Could not open the uploaded file.');
        }

        // Workable exports are usually tab-separated; this app's own export is comma-separated.
        // Detect the delimiter from the header line.
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return back()->with('error', 'The uploaded file is empty.');
        }
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine); // strip UTF-8 BOM
        $delimiter = substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'Invalid file format — no header row found.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));

        // Map each header => ALL its column indices. Workable repeats sections (Phone,
        // Salary, Bank, …) and the populated value is often in the second block, so we
        // keep every index and pick the last non-empty value below.
        $colMap = [];
        foreach ($header as $i => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') {
                $colMap[$key][] = $i;
            }
        }

        // app field => accepted header names (Workable export + this app's own export headers).
        $aliases = [
            'first_name'          => ['first name'],
            'last_name'           => ['last name'],
            'email'               => ['work email', 'email'],
            'employee_id'         => ['employee id'],
            'job_title'           => ['job title'],
            'phone'               => ['phone (phone)', 'phone'],
            'address'             => ['address'],
            'city'                => ['city'],
            'country'             => ['country'],
            'timezone'            => ['timezone'],
            'date_of_birth'       => ['birthdate', 'date of birth'],
            'nationality'         => ['nationality'],
            'gender'              => ['gender'],
            'languages'           => ['language', 'languages'],
            'linkedin_url'        => ['linkedin url'],
            'github_url'          => ['github url'],
            'portfolio_url'       => ['portfolio url'],
            'employee_status'     => ['employment status', 'employee status'],
            'contract_type'       => ['employment type (contract details)', 'contract type'],
            'salary'              => ['pay rate | amount (salary details)', 'salary'],
            'salary_currency'     => ['pay rate | currency (salary details)', 'salary currency'],
            'notice_period_days'  => ['notice period in days', 'notice period days'],
            'years_of_experience' => ['years of experience'],
            'education'           => ['education'],
            'specialization'      => ['specialization', 'skill'],
            'admin_notes'         => ['admin notes'],
            'hire_date'           => ['hire date'],
            'joined_at'           => ['start date', 'joined at'],
            'department'          => ['department'],
            'entity'              => ['entity'],
            'manager'             => ['reports to', 'reporting to', 'reports_to', 'line manager', 'manager name', 'manager email', 'manager (reports to)', 'manager'],
            'job_location'        => ['job location'],
        ];

        // Pick the LAST non-empty value across every occurrence of the given headers.
        $pick = function (array $row, array $headers) use ($colMap) {
            $found = null;
            foreach ($headers as $h) {
                foreach ($colMap[$h] ?? [] as $idx) {
                    $val = $row[$idx] ?? null;
                    if ($val !== null && trim((string) $val) !== '') {
                        $found = trim((string) $val);
                    }
                }
            }
            return $found;
        };
        $resolve = function (array $row, string $field) use ($aliases, $pick) {
            return $pick($row, $aliases[$field] ?? []);
        };
        $parseDate = function ($v) {
            if (empty($v)) return null;
            try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
        };

        // Profile-template fields → Workable export headers. Each is saved into the right
        // profile field (native column when fillable, else EmployeeFieldValue).
        $profileFieldsByKey = \App\Models\ProfileField::get()->keyBy('key');
        $authId = optional($request->user())->id ?? 1;
        $profileFieldAliases = [
            'personal_email'         => ['personal email'],
            'phone_number'           => ['phone (phone)', 'phone'],
            'phone_type'             => ['type (phone)'],
            'phone_extension'        => ['extension (phone)'],
            'date_of_birth'          => ['birthdate', 'date of birth'],
            'gender'                 => ['gender'],
            'home_address'           => ['address'],
            'preferred_name'         => ['preferred name'],
            'marital_status'         => ['status (marital status)'],
            'birthplace'             => ['birthplace'],
            'clothing_size'          => ['clothing size', 'size (clothing size)'],
            'social_security_number' => ['social security number', 'number (social security number)'],
            'team'                   => ['team'],
            'work_email'             => ['work email'],
            'job_title'              => ['job title'],
            'start_date'             => ['start date'],
            'contract_type'          => ['employment type (contract details)'],
            'employment_type'        => ['employment type (contract details)'],
            'work_location'          => ['workplace (contract details)'],
            'hire_date'              => ['hire date'],
            'notice_period_days'     => ['notice period in days'],
            'salary'                 => ['pay rate | amount (salary details)'],
            'pay_frequency'          => ['pay rate | frequency (salary details)'],
            'currency'               => ['pay rate | currency (salary details)'],
            'salary_effective_date'  => ['effective date (salary details)'],
            'employee_id'            => ['employee id'],
            'chat_type'              => ['type (chat & video call)'],
            'chat_username'          => ['username (chat & video call)'],
            'social_media_type'      => ['type (social media)'],
            'social_media_url'       => ['url (social media)'],
            'medical_conditions'     => ['medical conditions and allergies'],
            'blood_group'            => ['blood group'],
            'dietary_restrictions'   => ['dietary restrictions', 'dietry restrictions'],
            'probation_start_date'   => ['start date (probation)'],
            'probation_end_date'     => ['end date (probation)'],
            'probation_note'         => ['note (probation)'],
            'contract_effective_date' => ['effective date (contract details)'],
            'workplace'              => ['workplace (contract details)'],
            'contract_expiry_date'   => ['expiry date (contract details)'],
            'contract_note'          => ['note (contract details)'],
            'work_schedule'          => ['work schedule'],
            'employment_status'      => ['employment status'],
            'bank_name'              => ['bank name (bank details)'],
            'iban'                   => ['iban (bank details)'],
            'account_number'         => ['account number (bank details)'],
            'sort_code'              => ['sort code (bank details)'],
            'bic_swift'              => ['bic (swift) (bank details)'],
            'aba_transit_number'     => ['aba/transit number (bank details)'],
            'bank_state_branch'      => ['bank state branch (bsb) (bank details)'],
            'routing_number'         => ['routing number (bank details)'],
            'cnic_number'            => ['number (national identification number - cnic)'],
            'cnic_issue_date'        => ['issue date (national identification number - cnic)'],
            'sin_number'             => ['number (social insurance number)'],
            'sin_issue_date'         => ['issue date (social insurance number)'],
            'sin_insurance_carrier'  => ['insurance carrier (social insurance number)'],
            'tax_number'             => ['number (tax identification number)'],
            'tax_issue_date'         => ['issue date (tax identification number)'],
            'nationality'            => ['nationality'],
            'citizenship'            => ['citizenship'],
            'passport_country'       => ['country (passport details)'],
            'passport_number'        => ['number (passport details)'],
            'passport_issue_date'    => ['issue date (passport details)'],
            'passport_expiry_date'   => ['expiry date (passport details)'],
            'visa_country'           => ['country (visa details)'],
            'visa_type'              => ['type (visa details)'],
            'visa_number'            => ['number (visa details)'],
            'visa_issue_date'        => ['issue date (visa details)'],
            'visa_expiry_date'       => ['expiry date (visa details)'],
            'edu_start_date'         => ['start date (education details)'],
            'edu_end_date'           => ['end date (education details)'],
            'edu_degree'             => ['degree (education details)'],
            'edu_field_of_study'     => ['field of study (education details)'],
            'edu_school'             => ['school (education details)'],
            'exp_start_date'         => ['start date (job details)'],
            'exp_end_date'           => ['end date (job details)'],
            'exp_job_title'          => ['job title (job details)'],
            'exp_company'            => ['company (job details)'],
            'exp_summary'            => ['summary (job details)'],
            'exp_present'            => ['present (job details)'],
            'skill'                  => ['skill'],
            'language'               => ['language'],
            'emergency_name'         => ['name (contact details)'],
            'emergency_relationship' => ['relationship (contact details)'],
            'emergency_phone'        => ['phone (contact details)'],
            'emergency_email'        => ['email (contact details)'],
            'emergency_country'      => ['country (contact details)'],
            'emergency_address'      => ['address (contact details)'],
        ];

        $entityDefault = \App\Models\CompanyEntity::primary() ?? \App\Models\CompanyEntity::first();
        $role = \App\Models\Role::where('slug', 'employee')->first();

        $count = 0;
        $skipped = 0;
        $errors = 0;
        $managerRefs = []; // [user_id => raw "reports to" name], resolved after the loop
        while (($csvLine = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Skip fully blank lines
            if (count($csvLine) === 1 && trim((string) ($csvLine[0] ?? '')) === '') {
                continue;
            }

            $email = $resolve($csvLine, 'email');
            if (empty($email)) { $skipped++; continue; }

            // Company entity — match an existing one by name (do not auto-create)
            $entityId = $entityDefault->id ?? null;
            if ($entityName = $resolve($csvLine, 'entity')) {
                $entityId = \App\Models\CompanyEntity::where('name', $entityName)->value('id') ?? $entityId;
            }
            // Department — create if missing (supply required company_id / entity)
            $departmentId = null;
            if ($deptName = $resolve($csvLine, 'department')) {
                $departmentId = \App\Models\Department::firstOrCreate(
                    ['name' => $deptName],
                    ['company_id' => 1, 'company_entity_id' => $entityId]
                )->id;
            }
            // Manager ("Reports to") — captured here, linked in a second pass below
            // so a manager listed later in the file is still resolved.
            $managerName = $resolve($csvLine, 'manager');
            // Job location — match an existing one by name
            $jobLocationId = null;
            if ($jlName = $resolve($csvLine, 'job_location')) {
                $jobLocationId = \App\Models\JobLocation::where('name', $jlName)->value('id');
            }

            $salaryRaw = $resolve($csvLine, 'salary');
            $salary = $salaryRaw !== null ? (preg_replace('/[^0-9.]/', '', $salaryRaw) ?: null) : null;

            // employee_status is an enum('active','draft','inactive','offboarded').
            // Normalize the free-text Workable "Employment Status" (Active, Probationary,
            // Terminated, ...) into a valid enum value so the insert doesn't get truncated.
            $empStatusRaw = $resolve($csvLine, 'employee_status');
            $empStatus = null;
            if ($empStatusRaw !== null) {
                $s = strtolower($empStatusRaw);
                if (in_array($s, ['active', 'draft', 'inactive', 'offboarded'], true)) {
                    $empStatus = $s;
                } elseif (preg_match('/terminat|resign|offboard|left|former|ended/', $s)) {
                    $empStatus = 'offboarded';
                } elseif (preg_match('/inactive|suspend|hold|dormant/', $s)) {
                    $empStatus = 'inactive';
                } else {
                    $empStatus = 'active'; // active, employed, permanent, probationary, etc.
                }
            }

            $data = array_filter([
                'first_name'          => $resolve($csvLine, 'first_name'),
                'last_name'           => $resolve($csvLine, 'last_name'),
                'job_title'           => $resolve($csvLine, 'job_title'),
                'employee_id'         => $resolve($csvLine, 'employee_id'),
                'department_id'       => $departmentId,
                'company_entity_id'   => $entityId,
                'job_location_id'     => $jobLocationId,
                'hire_date'           => $parseDate($resolve($csvLine, 'hire_date')),
                'phone'               => $resolve($csvLine, 'phone'),
                'address'             => $resolve($csvLine, 'address'),
                'city'                => $resolve($csvLine, 'city'),
                'country'             => $resolve($csvLine, 'country'),
                'timezone'            => $resolve($csvLine, 'timezone'),
                'date_of_birth'       => $parseDate($resolve($csvLine, 'date_of_birth')),
                'nationality'         => $resolve($csvLine, 'nationality'),
                'gender'              => $resolve($csvLine, 'gender'),
                'languages'           => $resolve($csvLine, 'languages'),
                'linkedin_url'        => $resolve($csvLine, 'linkedin_url'),
                'github_url'          => $resolve($csvLine, 'github_url'),
                'portfolio_url'       => $resolve($csvLine, 'portfolio_url'),
                'employee_status'     => $empStatus,
                'joined_at'           => $parseDate($resolve($csvLine, 'joined_at')),
                'contract_type'       => $resolve($csvLine, 'contract_type'),
                'salary'              => $salary,
                'salary_currency'     => $resolve($csvLine, 'salary_currency'),
                'notice_period_days'  => $resolve($csvLine, 'notice_period_days'),
                'years_of_experience' => $resolve($csvLine, 'years_of_experience'),
                'education'           => $resolve($csvLine, 'education'),
                'specialization'      => $resolve($csvLine, 'specialization'),
                'admin_notes'         => $resolve($csvLine, 'admin_notes'),
            ], fn ($v) => $v !== null && $v !== '');

            try {
                $user = User::where('email', trim($email))->first();

                if ($user) {
                    $user->update($data);
                } else {
                    $data['company_id'] = 1;
                    $data['email'] = trim($email);
                    $data['password'] = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16));
                    $data['status'] = $data['status'] ?? 'active';
                    $data['employee_status'] = $data['employee_status'] ?? 'active';
                    $data['account_status'] = 'invited';
                    $data['must_change_password'] = true;

                    $user = User::create($data);

                    if ($role) {
                        $user->roles()->syncWithoutDetaching([$role->id]);
                    }
                }

                // Remember the "Reports to" value to link after every user exists.
                if (!empty($managerName)) {
                    $managerRefs[$user->id] = $managerName;
                }

                // Save every mapped Workable column into its profile field.
                foreach ($profileFieldAliases as $fkey => $fheaders) {
                    $field = $profileFieldsByKey->get($fkey);
                    if (!$field) {
                        continue;
                    }
                    $val = $pick($csvLine, $fheaders);
                    if ($val === null || $val === '') {
                        continue;
                    }
                    if ($field->type === 'date') {
                        $val = $parseDate($val) ?: $val;
                    }
                    try {
                        if ($user->isFillable($fkey) || array_key_exists($fkey, $user->getAttributes())) {
                            $user->update([$fkey => $val]);
                        } else {
                            \App\Models\EmployeeFieldValue::updateOrCreate(
                                ['user_id' => $user->id, 'field_id' => $field->id],
                                ['value' => $val, 'updated_by' => $authId]
                            );
                        }
                    } catch (\Throwable $e) {
                        // A single malformed field must not abort the row.
                    }
                }
                // Keep the template "Full name" field in sync with first/last name.
                if ($fnField = $profileFieldsByKey->get('full_name')) {
                    \App\Models\EmployeeFieldValue::updateOrCreate(
                        ['user_id' => $user->id, 'field_id' => $fnField->id],
                        ['value' => trim($user->first_name . ' ' . $user->last_name), 'updated_by' => $authId]
                    );
                }

                // Mirror into the legacy `employees` table so the imported person
                // appears in the /employees directory (which reads that table).
                $ct = strtolower((string) ($user->contract_type ?? ''));
                if (str_contains($ct, 'part')) {
                    $employmentType = 'part_time';
                } elseif (preg_match('/contract|probation|intern|temp|freelanc/', $ct)) {
                    $employmentType = 'contract';
                } else {
                    $employmentType = 'full_time';
                }
                $employeeStatus = ($user->employee_status === 'offboarded') ? 'terminated' : 'active';
                $hireDate = $user->hire_date ?? $user->joined_at ?? now();

                \App\Models\Employee::updateOrCreate(
                    ['email' => trim($email)],
                    [
                        'user_id'         => $user->id,
                        'company_id'      => $user->company_id ?? 1,
                        'department_id'   => $user->department_id,
                        'first_name'      => $user->first_name ?: 'Unknown',
                        'last_name'       => $user->last_name ?: '',
                        'job_title'       => $user->job_title ?: 'Employee',
                        'employee_id'     => $user->employee_id,
                        'employment_type' => $employmentType,
                        'hire_date'       => $hireDate,
                        'status'          => $employeeStatus,
                        'salary'          => $user->salary,
                        'currency'        => $user->salary_currency,
                        'phone'           => $user->phone,
                    ]
                );

                // Keep the job location's cached employee count in sync
                if (!empty($data['job_location_id'])) {
                    optional(\App\Models\JobLocation::find($data['job_location_id']))->refreshEmployeeCount();
                }

                $count++;
            } catch (\Throwable $e) {
                // One malformed row must not abort the whole import.
                $errors++;
                \Illuminate\Support\Facades\Log::warning("Employee import: row for '{$email}' failed — " . $e->getMessage());
                continue;
            }
        }

        fclose($handle);

        // Second pass: now that every user exists, resolve each "Reports to" name.
        // Handles Workable's "Last, First" format, plain "First Last", and email.
        $resolveManager = function (string $name) {
            $name = trim($name);
            if ($name === '') return null;
            if (str_contains($name, '@')) {
                return User::whereRaw('LOWER(email) = ?', [mb_strtolower($name)])->value('id');
            }
            if (str_contains($name, ',')) { // "Last, First"
                [$last, $first] = array_map('trim', array_pad(explode(',', $name, 2), 2, ''));
                if ($first !== '' && $last !== '') {
                    $id = User::whereRaw('LOWER(first_name) = ? AND LOWER(last_name) = ?', [mb_strtolower($first), mb_strtolower($last)])->value('id');
                    if ($id) return $id;
                }
            }
            $id = User::whereRaw("LOWER(CONCAT(first_name, ' ', last_name)) = ?", [mb_strtolower($name)])->value('id');
            if ($id) return $id;
            // Fall back to "Last First" (reversed, no comma)
            return User::whereRaw("LOWER(CONCAT(last_name, ' ', first_name)) = ?", [mb_strtolower($name)])->value('id');
        };

        $managersLinked = 0;
        foreach ($managerRefs as $userId => $managerName) {
            $managerId = $resolveManager($managerName);
            if ($managerId && (int) $managerId !== (int) $userId) {
                User::where('id', $userId)->update(['manager_id' => $managerId]);
                $managersLinked++;
            }
        }

        $msg = "Imported / updated {$count} employees.";
        if ($managersLinked > 0) {
            $msg .= " Linked {$managersLinked} manager relationship(s).";
        }
        if ($skipped > 0) {
            $msg .= " Skipped {$skipped} row(s) with no work email.";
        }
        if ($errors > 0) {
            $msg .= " {$errors} row(s) had errors and were skipped (see logs).";
        }
        return back()->with('success', $msg);
    }
}
