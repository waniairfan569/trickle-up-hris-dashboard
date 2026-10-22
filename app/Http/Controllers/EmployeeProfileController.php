<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ProfileField;
use App\Models\EmployeeFieldValue;
use App\Models\ProfileTemplate;
use App\Services\HRPermissionService;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\Storage;

class EmployeeProfileController extends Controller
{
    use LogsActivity;

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

        // Admin-only conduct / behaviour log (shown in the Time tracking tab).
        $conductNotes = $auth->isAdmin()
            ? \App\Models\ConductNote::where('user_id', $employee->id)->with('author', 'document')
                ->orderByDesc('occurred_on')->orderByDesc('id')->limit(100)->get()
            : collect();

        // Admin-only lateness / return-to-work document tracking (Time tracking tab).
        $latenessDocData = $auth->isAdmin() ? $this->buildLatenessDocData($employee) : null;

        return view('employees.profile.show', compact('employee', 'templates', 'canEdit', 'allUsers', 'signatureDocs', 'payReviews', 'probation', 'conductNotes', 'latenessDocData'));
    }

    /**
     * Late days (last 90d) and returned leaves, each paired with its auto-generated
     * document + status, for the Time-tracking tracking panel.
     */
    private function buildLatenessDocData(\App\Models\User $employee): array
    {
        // Resolved by the generator itself, so the panel can never look for a
        // template the nightly job wouldn't have used (or vice versa).
        $generator   = app(\App\Services\HrDocumentAutoGenerator::class);
        $latenessTpl = $generator->latenessTemplate();
        $absenceTpl  = $generator->absenceTemplate();

        $window = \App\Services\HrDocumentAutoGenerator::LOOKBACK_DAYS;
        $from = now()->subDays($window)->toDateString();

        // Status from a document (or null).
        $status = function ($doc) {
            if (! $doc) {
                return 'none';
            }
            if ($doc->signers->isNotEmpty() && $doc->signers->every(fn ($s) => $s->signed_at)) {
                return 'signed';
            }
            return in_array($doc->status, ['sent', 'completed'], true) ? $doc->status : 'draft';
        };

        // Late days, what the employee said about each, and the month-end
        // review that covers it. Per-day documents are no longer drafted, but
        // older ones are still shown where they exist.
        $lateDays = \App\Models\AttendanceRecord::where('user_id', $employee->id)
            ->where('status', 'late')->whereDate('date', '>=', $from)
            ->orderByDesc('date')->get(['id', 'date', 'late_minutes']);

        $explanations = \App\Models\AttendanceExplanation::where('user_id', $employee->id)
            ->whereDate('date', '>=', $from)->get()
            ->keyBy(fn ($e) => $e->date->toDateString());

        $latenessDocs = $latenessTpl
            ? \App\Models\HrDocument::where('user_id', $employee->id)
                ->where('hr_document_template_id', $latenessTpl->id)
                ->with('signers')->get()
            : collect();

        // A day is covered by whichever review spans it — per-day or monthly.
        $docForDay = function ($date) use ($latenessDocs) {
            return $latenessDocs->first(fn ($d) => $d->period_start && $d->period_end
                && $date->betweenIncluded($d->period_start, $d->period_end));
        };

        $latenessRows = $lateDays->map(fn ($r) => (object) [
            'date'         => $r->date,
            'late_minutes' => $r->late_minutes,
            'explanation'  => $explanations->get($r->date->toDateString()),
            'doc'          => $doc = $docForDay($r->date),
            'status'       => $status($doc),
        ])->values();

        // Returned leaves (unplanned / sick / casual / emergency / WFH — not planned)
        // + their Return-to-Work documents. Deduped by period so the same dates
        // (e.g. two leaves that day) show once.
        $leaves = \App\Models\TimeOffRequest::where('user_id', $employee->id)
            ->where('status', 'approved')->returnToWorkEligible()
            ->whereDate('end_date', '<', now()->toDateString())
            ->whereDate('end_date', '>=', $from)
            ->with('policy')->orderByDesc('start_date')->get()
            ->unique(fn ($lv) => \Illuminate\Support\Carbon::parse($lv->start_date)->toDateString() . '|' . \Illuminate\Support\Carbon::parse($lv->end_date)->toDateString())
            ->values();

        // A leave return may use the return-to-work, hourly or WFH form, so look
        // for its document under any of them.
        $returnTplIds = $generator->returnTemplateIds();

        $absenceDocs = $returnTplIds
            ? \App\Models\HrDocument::where('user_id', $employee->id)
                ->whereIn('hr_document_template_id', $returnTplIds)
                ->with('signers')->get()
                ->keyBy(fn ($d) => optional($d->period_start)->toDateString() . '|' . optional($d->period_end)->toDateString())
            : collect();

        $returnRows = $leaves->map(function ($lv) use ($absenceDocs, $status, $generator) {
            $start = \Illuminate\Support\Carbon::parse($lv->start_date);
            $key = $start->toDateString() . '|' . \Illuminate\Support\Carbon::parse($lv->end_date)->toDateString();
            $template = $generator->returnTemplateFor($lv);

            // A form drafted per leave has exactly this leave's dates. A template
            // set to one a month covers the whole month instead, so fall back to
            // the document whose period spans the leave — otherwise every day in
            // a rolled-up month reads "Not generated" while its document exists.
            $doc = $absenceDocs->get($key);
            if (! $doc && $template) {
                $doc = $absenceDocs->first(fn ($d) => $d->hr_document_template_id === $template->id
                    && $d->period_start && $d->period_end
                    && $start->betweenIncluded($d->period_start, $d->period_end));
            }

            return (object) [
                'leave'  => $lv,
                'doc'    => $doc,
                'status' => $status($doc),
                // "Not generated" is unhelpful when the real reason is that this
                // kind of leave has no document assigned to it at all.
                'noTemplate' => ! $template,
            ];
        })->values();

        return [
            'hasLatenessTpl' => (bool) $latenessTpl,
            'hasAbsenceTpl'  => ! empty($returnTplIds),
            'latenessRows'   => $latenessRows,
            'returnRows'     => $returnRows,
            'windowDays'     => $window,
            'latenessMonths' => collect(range(0, 2))->map(fn ($i) => now()->subMonths($i)->format('Y-m')),
        ];
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

    /**
     * Write the title move to the audit log, and draft the letter it calls for.
     *
     * The log entry happens whether or not a template is assigned, because the
     * move itself is worth a record: once applied, the employee's own row only
     * knows where they ended up. The letter is handed both titles directly —
     * nothing can look up the old one afterwards.
     *
     * Neither half may break saving a profile.
     */
    private function recordTitleChange(User $employee, string $from, string $to, ?User $by): ?\App\Models\HrDocument
    {
        try {
            $this->logActivity(
                'updated', 'User', $employee->id,
                "{$employee->full_name}'s job title changed from “{$from}” to “{$to}”",
                ['previous_job_title' => $from, 'new_job_title' => $to]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            return app(\App\Services\HrDocumentAutoGenerator::class)->generateForEvent(
                'title_change', $employee, now(), $by?->id,
                ['previous_job_title' => $from, 'new_job_title' => $to]
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
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

        // Held before the fields are written, because the save overwrites it and
        // the title somebody moved FROM is then nowhere to be read.
        $titleBefore = (string) ($employee->job_title ?? '');

        $fields = $request->input('fields', []);

        if ($request->hasFile('fields')) {
            $fields = array_merge($fields, $request->file('fields'));
        }

        $emailConflict = null;
        $notCleared = [];
        $awaitingReview = [];
        $savedAny = false;

        foreach ($fields as $key => $value) {
            $field = ProfileField::where('key', $key)->first();

            if (!$field || !$field->isEditableTo($auth, $employee)) {
                continue;
            }

            // Multi-selects post a blank sentinel so "none ticked" still arrives — drop it.
            if (is_array($value)) {
                $value = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
            }

            // A field that was sent empty means the user cleared it, so write null
            // (or drop the dynamic value) instead of silently keeping the old one.
            if (!$request->hasFile("fields.{$key}") && (is_null($value) || $value === '' || $value === [])) {
                // An empty file input means "keep the current file", not "remove it".
                if ($field->type === 'file') {
                    continue;
                }

                $isNative = $employee->isFillable($key) || array_key_exists($key, $employee->getAttributes());
                $current = $isNative
                    ? $employee->getAttribute($key)
                    : EmployeeFieldValue::where('user_id', $employee->id)->where('field_id', $field->id)->value('value');

                // Required fields can't be blanked (several are NOT NULL columns,
                // e.g. employee_status) — keep the value and say so.
                if ($field->is_required) {
                    if (filled($current)) {
                        $notCleared[] = $field->name;
                    }
                    continue;
                }

                if ($isNative) {
                    try {
                        $employee->update([$key => null]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        $notCleared[] = $field->name;
                    }
                } else {
                    EmployeeFieldValue::where('user_id', $employee->id)->where('field_id', $field->id)->delete();
                }
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

            // Some fields are not the employee's to change on their own say-so:
            // the workspace can ask to be told, or to approve first. Only their
            // own edits pass through this — an admin editing somebody's record
            // is already the check.
            $held = app(\App\Services\ProfileChangeGuard::class)->intercept(
                $employee, $auth, $field, $value,
                $employee->isFillable($key) || array_key_exists($key, $employee->getAttributes())
                    ? $employee->{$key}
                    : EmployeeFieldValue::where('user_id', $employee->id)->where('field_id', $field->id)->value('value')
            );

            if ($held) {
                $awaitingReview[] = $field->name ?: $key;
                continue;
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

            $savedAny = true;

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

        // A job title changed here and nowhere else — a re-grade or a sideways
        // move, with no money attached. A promotion that comes with pay belongs
        // on a pay review, which raises its own letter; this covers the rest,
        // which until now changed the record and told nobody.
        $titleAfter = (string) ($employee->fresh()->job_title ?? '');
        $titleMoved = $titleBefore !== '' && $titleAfter !== '' && $titleBefore !== $titleAfter;
        $titleLetter = null;

        if ($titleMoved) {
            $titleLetter = $this->recordTitleChange($employee, $titleBefore, $titleAfter, $auth);
        }

        // Bug #3 Fix: Redirect to view mode after save (not back to ?edit=1)
        // Return to the sub-tab being edited; the view validates it against what the viewer may see.
        $tab = preg_match('/^[a-z]+$/', (string) $request->input('tab')) ? $request->input('tab') : null;
        $redirect = redirect()->route('employees.profile', array_filter(['employee' => $employee->id, 'tab' => $tab]))
            ->with('success', $titleLetter
                ? "Profile updated. Job title changed from “{$titleBefore}” to “{$titleAfter}”, and a letter has been drafted for you to review and send."
                : ($titleMoved
                    ? "Profile updated. Job title changed from “{$titleBefore}” to “{$titleAfter}”."
                    : 'Profile updated successfully.'));

        // Anything the workspace holds for approval has not been written, and
        // saying "updated" about it would be a lie.
        if ($awaitingReview) {
            $held = count($awaitingReview) === 1
                ? "“{$awaitingReview[0]}” has been sent to HR to approve. It will change once they do."
                : collect($awaitingReview)->map(fn ($f) => "“{$f}”")->join(', ', ' and ')
                    . ' have been sent to HR to approve. They will change once HR does.';

            // When everything they touched is being held, nothing was updated
            // at all, so that is the whole message rather than a footnote.
            $savedAny
                ? $redirect->with('warning', $held)
                : $redirect->with('success', $held);
        }

        if ($emailConflict) {
            $redirect->with('warning', "The work email was saved, but the sign-in address and header couldn't be changed to \"{$emailConflict}\" because another account already uses it.");
        } elseif ($notCleared) {
            $redirect->with('warning', 'These fields are required and kept their value: ' . implode(', ', array_unique($notCleared)) . '.');
        }

        return $redirect;
    }
}
