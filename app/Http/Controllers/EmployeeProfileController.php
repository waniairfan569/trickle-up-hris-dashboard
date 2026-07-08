<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ProfileField;
use App\Models\EmployeeFieldValue;
use App\Models\ProfileTemplate;
use App\Services\HRPermissionService;
use Illuminate\Support\Facades\Storage;

class EmployeeProfileController extends Controller
{
    protected $hrPermissionService;

    public function __construct(HRPermissionService $hrPermissionService)
    {
        $this->hrPermissionService = $hrPermissionService;
    }

    public function show(User $employee)
    {
        $auth = auth()->user();

        // All authenticated employees can view any colleague's profile (read-only)
        // Editing is separately controlled via $canEdit

        $employee->load('fieldValues.field', 'department', 'manager', 'documents.uploader');

        // Workable model: the DEFAULT template applies to every employee automatically
        // (it is never individually assigned). Dynamic templates appear ONLY when the
        // employee has been explicitly assigned them.
        $defaultTemplates = ProfileTemplate::with('sections.fields')
            ->active()
            ->where('type', 'default')
            ->get();
        $assignedDynamic = $employee->profileTemplates()
            ->with('sections.fields')
            ->where('type', 'dynamic')
            ->get();
        $templates = $defaultTemplates->merge($assignedDynamic);

        // Filter fields by visibility
        foreach ($templates as $template) {
            foreach ($template->sections as $section) {
                $section->setRelation('fields', $section->fields->filter(function ($field) use ($auth, $employee) {
                    return $field->isVisibleTo($auth, $employee);
                }));
            }
        }

        $canEdit = $this->hrPermissionService->canEditEmployee($auth, $employee);

        // Pass all employees for manager dropdown (excluding self), with department for context
        $allUsers = User::orderBy('first_name')->where('id', '!=', $employee->id)->with('department')->get();

        // Probation — latest record + history (Job tab).
        $probation = $employee->probations()->with('events.reviewer:id,first_name,last_name')->latest('id')->first();

        // Pay reviews / salary history (Compensation tab), with derived statuses.
        $payReviews = \App\Models\PayReview::withStatuses(
            $employee->payReviews()->with('approver:id,first_name,last_name')->get()
        );

        // E-signature documents involving this employee (as subject or a signer)
        $signatureRequests = \App\Models\DocumentRequest::with(['template', 'signers.user'])
            ->where('subject_employee_id', $employee->id)
            ->orWhereHas('signers', fn ($q) => $q->where('user_id', $employee->id))
            ->latest()
            ->get();

        return view('employees.profile.show', compact('employee', 'templates', 'canEdit', 'allUsers', 'signatureRequests', 'payReviews', 'probation'));
    }

    public function edit(User $employee)
    {
        $auth = auth()->user();

        // Authorize: admin, self, or manager
        if (!$auth->isAdmin() && $auth->id !== $employee->id && $auth->id !== $employee->manager_id) {
            if (!$this->hrPermissionService->canEditEmployee($auth, $employee)) {
                abort(403, 'Unauthorized access to edit employee profile.');
            }
        }

        return redirect()->route('employees.profile', ['employee' => $employee->id, 'edit' => 1]);
    }

    public function update(Request $request, User $employee)
    {
        $auth = auth()->user();

        if (!$auth->isAdmin() && $auth->id !== $employee->id && $auth->id !== $employee->manager_id) {
            if (!$this->hrPermissionService->canEditEmployee($auth, $employee)) {
                abort(403, 'Unauthorized access to edit employee profile.');
            }
        }

        // Bug #6 Fix: Handle profile avatar/photo upload
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'avatar_' . $employee->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $filename, 'public');
            $employee->update(['avatar_url' => Storage::url($path)]);
        }

        // Handle ZKTeco columns if Admin
        if ($auth->hasRole('super_admin') || $auth->hasRole('hr_admin')) {
            if ($request->has('zkteco_uid')) {
                $employee->zkteco_uid = $request->input('zkteco_uid');
                $employee->zkteco_employee_id = $request->input('zkteco_employee_id');
                $employee->save();
            }
        }

        $fields = $request->input('fields', []);
        
        if ($request->hasFile('fields')) {
            $fields = array_merge($fields, $request->file('fields'));
        }

        foreach ($fields as $key => $value) {
            $field = ProfileField::where('key', $key)->first();
            
            if (!$field || !$field->isEditableTo($auth, $employee)) {
                continue;
            }

            // Partial save: skip empty, non-file values so a blank field neither
            // blocks the save nor overwrites data the employee already entered.
            if (!$request->hasFile("fields.{$key}") && (is_null($value) || $value === '' || (is_array($value) && count($value) === 0))) {
                continue;
            }

            // Handle specific field types
            if ($field->type === 'file' && $request->hasFile("fields.{$key}")) {
                $file = $request->file("fields.{$key}");
                $path = $file->store("employee-files/{$employee->id}/{$key}", 'public');
                $value = Storage::url($path);
            } elseif (in_array($field->type, ['multi_select', 'date_range']) && is_array($value)) {
                $value = json_encode($value);
            }

            // Save native user model attributes, or dynamic EmployeeFieldValue
            if ($employee->isFillable($key) || array_key_exists($key, $employee->getAttributes())) {
                $employee->update([$key => $value]);
            } else {
                EmployeeFieldValue::updateOrCreate(
                    ['user_id' => $employee->id, 'field_id' => $field->id],
                    ['value' => $value, 'updated_by' => $auth->id]
                );
            }

            // "Full name" isn't a native column — keep first/last name in sync when it's edited.
            if ($key === 'full_name' && is_string($value) && trim($value) !== '') {
                $parts = preg_split('/\s+/', trim($value), 2);
                $employee->update(['first_name' => $parts[0], 'last_name' => $parts[1] ?? '']);
            }
        }

        // Keep the Employee row's department in sync with the profile: the
        // directory reads employees.department_id, but the profile writes
        // users.department_id — mirror it so both always agree.
        \App\Models\Employee::where('user_id', $employee->id)
            ->update(['department_id' => $employee->department_id]);

        // Bug #3 Fix: Redirect to view mode after save (not back to ?edit=1)
        return redirect()->route('employees.profile', $employee->id)
            ->with('success', 'Profile updated successfully.');
    }
}
