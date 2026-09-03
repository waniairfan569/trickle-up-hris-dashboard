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

        // Signature documents involving this employee — unified across BOTH signing
        // systems so everything they've signed shows in one place: the e-sign
        // "DocumentRequest" flow AND the HR "To-Sign" (HrDocument) flow.
        $auth = auth()->user();

        $esBadge = [
            'in_progress' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            'completed'   => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            'declined'    => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
            'cancelled'   => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        ];
        $esLabel = ['in_progress' => 'In progress', 'completed' => 'Completed', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];
        $amber = 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400';
        $emerald = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400';
        $slate = 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';

        // 1) E-sign requests (Employee Contract, NDA, …). Conditions are grouped so
        //    the tenant scope is never bypassed by the OR.
        $requests = \App\Models\DocumentRequest::with(['template', 'signers.user'])
            ->where(fn ($q) => $q->where('subject_employee_id', $employee->id)
                ->orWhereHas('signers', fn ($s) => $s->where('user_id', $employee->id)))
            ->latest()->get()
            ->map(function ($req) use ($auth, $employee, $esBadge, $esLabel) {
                $signed = $req->signers->where('status', 'signed')->count();
                return (object) [
                    'name'       => $req->template->name ?? 'Document',
                    'meta'       => "{$signed}/{$req->signers->count()} signed · " . $req->created_at->format('d M Y'),
                    'badge'      => $esBadge[$req->status] ?? 'bg-slate-100 text-slate-600',
                    'label'      => $esLabel[$req->status] ?? ucfirst($req->status),
                    'sortAt'     => $req->created_at,
                    'awaitingMe' => $req->isAwaiting($auth),
                    'signUrl'    => route('documents.sign', $req),
                    'viewUrl'    => route('documents.show', $req),
                    'deleteUrl'  => $auth->isAdmin() ? route('employees.signature-docs.destroy', [$employee, 'request', $req->id]) : null,
                ];
            });

        // 2) HR "To-Sign" documents (Lateness Review, Unplanned Leave, …): the ones
        //    the employee is a signer on, plus sent/completed ones about them.
        $hrDocs = \App\Models\HrDocument::with('signers')
            ->where(fn ($q) => $q->whereHas('signers', fn ($s) => $s->where('user_id', $employee->id))
                ->orWhere(fn ($w) => $w->where('user_id', $employee->id)->whereIn('status', ['sent', 'completed'])))
            ->latest()->get()
            ->map(function ($doc) use ($auth, $employee, $amber, $emerald, $slate) {
                $total    = $doc->signers->count();
                $signed   = $doc->signers->whereNotNull('signed_at')->count();
                $isSigned = $total > 0 && $signed === $total;
                $mine     = $doc->signers->firstWhere('user_id', $auth->id);

                if ($isSigned) {
                    [$label, $badge] = ['Signed', $emerald];
                } elseif ($signed > 0) {
                    [$label, $badge] = ['Partially signed', $amber];
                } elseif ($doc->status === 'sent') {
                    [$label, $badge] = ['Awaiting signature', $amber];
                } else {
                    [$label, $badge] = [ucfirst($doc->status), $slate];
                }

                return (object) [
                    'name'       => $doc->template_name ?: ($doc->title ?: 'HR document'),
                    'meta'       => ($total ? "{$signed}/{$total} signed · " : 'Sent ') . $doc->created_at->format('d M Y'),
                    'badge'      => $badge,
                    'label'      => $label,
                    'sortAt'     => $doc->created_at,
                    'awaitingMe' => (bool) ($mine && ! $mine->signed_at),
                    'signUrl'    => ($mine && ! $mine->signed_at) ? route('hr-documents.sign', $doc) : null,
                    'viewUrl'    => $auth->isAdmin()
                        ? route('hr-documents.show', $doc)
                        : ($mine ? route('hr-documents.my-pdf', ['document' => $doc, 'preview' => 1]) : null),
                    'deleteUrl'  => $auth->isAdmin() ? route('employees.signature-docs.destroy', [$employee, 'hr', $doc->id]) : null,
                ];
            });

        $signatureDocs = $requests->concat($hrDocs)->sortByDesc('sortAt')->values();

        return view('employees.profile.show', compact('employee', 'templates', 'canEdit', 'allUsers', 'signatureDocs', 'payReviews', 'probation'));
    }

    /**
     * Admin: delete a signature document from an employee's profile — either an
     * e-sign request (permanent, with its signers/events + any auto-filed copy)
     * or an HR "To-Sign" document (soft-deleted to the HR documents trash).
     */
    public function destroySignatureDoc(Request $request, User $employee, string $type, int $id)
    {
        abort_unless(optional(auth()->user())->isAdmin(), 403, 'Only an admin can delete documents from a profile.');

        if ($type === 'request') {
            $req = \App\Models\DocumentRequest::where('id', $id)
                ->where(fn ($q) => $q->where('subject_employee_id', $employee->id)
                    ->orWhereHas('signers', fn ($s) => $s->where('user_id', $employee->id)))
                ->firstOrFail();

            \App\Models\EmployeeDocument::where('source_type', 'signature')
                ->where('source_id', $req->id)->delete();
            $req->signers()->delete();
            $req->events()->delete();
            $req->delete();
        } elseif ($type === 'hr') {
            $doc = \App\Models\HrDocument::where('id', $id)
                ->where(fn ($q) => $q->where('user_id', $employee->id)
                    ->orWhereHas('signers', fn ($s) => $s->where('user_id', $employee->id)))
                ->firstOrFail();

            $doc->delete(); // soft delete — recoverable from HR documents trash
        } else {
            abort(404);
        }

        return back()->with('success', 'Document removed from the profile.');
    }

    public function edit(User $employee)
    {
        $auth = auth()->user();

        // Authorize: admin, self, or manager
        if (!$auth->isAdmin() && $auth->id !== $employee->id && !$auth->managesUser($employee->id)) {
            if (!$this->hrPermissionService->canEditEmployee($auth, $employee)) {
                abort(403, 'Unauthorized access to edit employee profile.');
            }
        }

        return redirect()->route('employees.profile', ['employee' => $employee->id, 'edit' => 1]);
    }

    public function update(Request $request, User $employee)
    {
        $auth = auth()->user();

        if (!$auth->isAdmin() && $auth->id !== $employee->id && !$auth->managesUser($employee->id)) {
            if (!$this->hrPermissionService->canEditEmployee($auth, $employee)) {
                abort(403, 'Unauthorized access to edit employee profile.');
            }
        }

        // Bug #6 Fix: Handle profile avatar/photo upload
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'avatar_' . $employee->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs(\App\Tenancy\TenantStorage::path('avatars'), $filename, 'public');
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

        $emailConflict = null;

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
                $path = $file->store(\App\Tenancy\TenantStorage::path("employee-files/{$employee->id}/{$key}"), 'public');
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

            // "Work email" is the account's login address and the one shown in the
            // profile header (users.email). It's stored as a profile-field value too,
            // so without this they drift: the field would change but the header/login
            // would keep the old address. Mirror it into users.email (+ the legacy
            // employees row) so everything stays in agreement — unless another
            // account already uses that address.
            if ($key === 'work_email' && is_string($value)) {
                $newEmail = trim($value);
                if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)
                    && strcasecmp($newEmail, (string) $employee->email) !== 0) {
                    $taken = \App\Models\User::where('email', $newEmail)
                        ->where('id', '!=', $employee->id)->exists()
                        || \App\Models\Employee::where('email', $newEmail)
                            ->where('user_id', '!=', $employee->id)->exists();
                    if ($taken) {
                        $emailConflict = $newEmail;
                    } else {
                        $employee->update(['email' => $newEmail]);
                        \App\Models\Employee::where('user_id', $employee->id)
                            ->update(['email' => $newEmail]);
                    }
                }
            }
        }

        // Additional managers (admin only — the manager field is admin-editable).
        // Sync the pivot, excluding self and the primary manager to avoid dupes.
        if ($auth->isAdmin()) {
            $additionalManagerIds = collect($request->input('additional_manager_ids', []))
                ->map(fn ($id) => (int) $id)->filter()
                ->reject(fn ($id) => $id === (int) $employee->id)
                ->reject(fn ($id) => $id === (int) $employee->manager_id)
                ->unique()->values()->all();
            $employee->additionalManagers()->sync($additionalManagerIds);
        }

        // Keep the Employee row's denormalised copies in sync with the profile:
        // the directory reads employees.{first_name,last_name,job_title,
        // department_id} for display + sort + search, but the profile writes the
        // users.* columns — mirror them so the two can never drift (which is what
        // made a profile-set job title still read as "Employee" in the directory).
        \App\Models\Employee::where('user_id', $employee->id)
            ->update([
                'department_id' => $employee->department_id,
                'first_name'    => $employee->first_name,
                'last_name'     => $employee->last_name,
                // employees.job_title is NOT NULL — coalesce so a user with no
                // title mirrors as an empty string instead of throwing.
                'job_title'     => (string) $employee->job_title,
            ]);

        // Bug #3 Fix: Redirect to view mode after save (not back to ?edit=1)
        $redirect = redirect()->route('employees.profile', $employee->id)
            ->with('success', 'Profile updated successfully.');

        if ($emailConflict) {
            $redirect->with('warning', "The work email was saved, but the sign-in address and header couldn't be changed to \"{$emailConflict}\" because another account already uses it.");
        }

        return $redirect;
    }
}
