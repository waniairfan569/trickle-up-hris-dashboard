<?php

namespace App\Http\Controllers;

use App\Exports\FormResponsesExport;
use App\Models\CompanyForm;
use App\Models\Department;
use App\Models\FormAssignment;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\Notifications\FormAssigned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class CompanyFormController extends Controller
{
    private const TYPES = ['text', 'textarea', 'number', 'email', 'phone', 'date', 'dropdown', 'multi_select', 'checkbox', 'radio', 'file_upload', 'heading', 'paragraph', 'divider', 'signature'];

    public function index(Request $request)
    {
        $query = CompanyForm::withCount(['fields', 'submissions'])
            ->with('assignments')
            ->latest();

        // Month filter (YYYY-MM) on when the form was created.
        $month = $request->input('month');
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $query->whereBetween('created_at', [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()]);
        } else {
            $month = null;
        }

        $forms = $query->get()
            ->each(function ($form) {
                $form->assigned_count = $form->getAssignedUsersFor()->count();
                $form->submitted_count = $form->submissions()->where('status', 'submitted')->count();
            });

        return view('company-forms.index', compact('forms', 'month'));
    }

    /** Admin: archived (soft-deleted) forms — restore or delete permanently. */
    public function archived()
    {
        $forms = CompanyForm::onlyTrashed()->withCount(['fields', 'submissions'])
            ->latest('deleted_at')
            ->get();

        return view('company-forms.archived', compact('forms'));
    }

    /** Admin: restore an archived form back to the active list. */
    public function restore(int $companyForm)
    {
        $form = CompanyForm::onlyTrashed()->findOrFail($companyForm);
        $form->restore();

        return back()->with('success', 'Form restored.');
    }

    /** Admin: permanently delete an archived form (and its dependents). */
    public function forceDelete(int $companyForm)
    {
        $form = CompanyForm::onlyTrashed()->findOrFail($companyForm);
        $form->forceDelete();

        return redirect()->route('company-forms.archived')->with('success', 'Form permanently deleted.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string|max:1000']);

        $form = CompanyForm::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'created_by' => auth()->id(),
            'company_id' => null,
        ]);

        return redirect()->route('company-forms.builder', $form)->with('success', 'Form created. Add your fields.');
    }

    public function builder(CompanyForm $companyForm)
    {
        $companyForm->load('fields');

        return view('company-forms.create', ['form' => $companyForm, 'types' => self::TYPES]);
    }

    /** Admin read-only preview — renders the form exactly as employees see it, works on drafts. */
    public function preview(CompanyForm $companyForm)
    {
        $companyForm->load('fields');

        return view('company-forms.preview', ['form' => $companyForm]);
    }

    /** Update form-level details / settings / status. */
    public function update(Request $request, CompanyForm $companyForm)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'deadline' => 'nullable|date',
            'status' => ['nullable', Rule::in(['draft', 'active', 'closed'])],
        ]);

        $companyForm->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'deadline' => $validated['deadline'] ?? null,
            'status' => $validated['status'] ?? $companyForm->status,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'allow_multiple_submissions' => $request->boolean('allow_multiple_submissions'),
            'is_monthly' => $request->boolean('is_monthly'),
            'show_progress_bar' => $request->boolean('show_progress_bar'),
            'requires_signature' => $request->boolean('requires_signature'),
        ]);

        // Overtime designation is exclusive — only one form drives the Time-Off
        // "Overtime Approval" button. Setting it here clears it from any other.
        if ($request->boolean('is_overtime_form')) {
            CompanyForm::where('system_key', 'overtime')
                ->where('id', '!=', $companyForm->id)
                ->update(['system_key' => null]);
            $companyForm->update(['system_key' => 'overtime', 'allow_multiple_submissions' => true]);
        } elseif ($companyForm->system_key === 'overtime') {
            $companyForm->update(['system_key' => null]);
        }

        return back()->with('success', 'Form settings saved.');
    }

    public function addField(Request $request, CompanyForm $companyForm)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'label' => 'required|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:255',
            'is_required' => 'nullable|boolean',
            'width' => ['nullable', Rule::in(['full', 'half'])],
            'options_raw' => 'nullable|string',
        ]);

        $options = null;
        if (in_array($validated['type'], ['dropdown', 'multi_select', 'radio'], true)) {
            $options = collect(preg_split('/[\r\n]+/', (string) $request->input('options_raw', '')))
                ->map(fn ($o) => trim($o))->filter()->values()->all();
        }

        $companyForm->fields()->create([
            'label' => $validated['label'],
            'field_key' => $this->uniqueKey($companyForm, $validated['label']),
            'type' => $validated['type'],
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
            'options' => $options,
            'is_required' => in_array($validated['type'], FormField::LAYOUT_TYPES, true) ? false : $request->boolean('is_required'),
            'width' => $validated['width'] ?? 'full',
            'sort_order' => (int) $companyForm->fields()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Field added.');
    }

    public function updateField(Request $request, FormField $field)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:255',
            'is_required' => 'nullable|boolean',
            'width' => ['nullable', Rule::in(['full', 'half'])],
            'options_raw' => 'nullable|string',
        ]);

        $options = $field->options;
        if (in_array($field->type, ['dropdown', 'multi_select', 'radio'], true)) {
            $options = collect(preg_split('/[\r\n]+/', (string) $request->input('options_raw', '')))
                ->map(fn ($o) => trim($o))->filter()->values()->all();
        }

        $field->update([
            'label' => $validated['label'],
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
            'options' => $options,
            'is_required' => in_array($field->type, FormField::LAYOUT_TYPES, true) ? false : $request->boolean('is_required'),
            'width' => $validated['width'] ?? 'full',
        ]);

        return back()->with('success', 'Field updated.');
    }

    public function deleteField(FormField $field)
    {
        $field->delete();

        return back()->with('success', 'Field removed.');
    }

    /** Move a field up or down in order. */
    public function moveField(Request $request, FormField $field)
    {
        $dir = $request->input('direction') === 'up' ? 'up' : 'down';
        $form = $field->form;
        $ordered = $form->fields()->get();
        $index = $ordered->search(fn ($f) => $f->id === $field->id);
        $swapWith = $dir === 'up' ? $index - 1 : $index + 1;

        if (isset($ordered[$swapWith])) {
            $other = $ordered[$swapWith];
            $a = $field->sort_order;
            $b = $other->sort_order;
            // Ensure distinct values before swapping.
            if ($a === $b) {
                $b = $a + ($dir === 'up' ? -1 : 1);
            }
            $field->update(['sort_order' => $b]);
            $other->update(['sort_order' => $a]);
        }

        return back();
    }

    public function assign(Request $request, CompanyForm $companyForm)
    {
        $validated = $request->validate([
            'assigned_to_type' => ['required', Rule::in(['user', 'department', 'all'])],
            'assigned_to_id' => 'nullable|integer',
        ]);

        $assignedToId = $validated['assigned_to_id'] ?? null;

        if (in_array($validated['assigned_to_type'], ['user', 'department'], true) && !$assignedToId) {
            return back()->withErrors(['assigned_to_id' => 'Select who to assign the form to.']);
        }

        $assignment = FormAssignment::firstOrCreate(
            [
                'form_id' => $companyForm->id,
                'assigned_to_type' => $validated['assigned_to_type'],
                'assigned_to_id' => $validated['assigned_to_type'] === 'all' ? null : $assignedToId,
            ],
            ['assigned_by' => auth()->id(), 'assigned_at' => now()]
        );

        // Monthly forms open for the current month; one-off forms have no period.
        $period = $companyForm->is_monthly ? $companyForm->currentPeriod() : null;

        // Create a pending submission for each targeted user + notify.
        $count = 0;
        foreach ($companyForm->getAssignedUsersFor() as $user) {
            $sub = FormSubmission::firstOrCreate(
                ['form_id' => $companyForm->id, 'user_id' => $user->id, 'period' => $period],
                ['assignment_id' => $assignment->id, 'status' => 'pending']
            );
            if ($sub->wasRecentlyCreated) {
                $count++;
                try {
                    $user->notify(new FormAssigned($companyForm, $period));
                } catch (\Throwable $e) {
                    // ignore per-user notification failure
                }
            }
        }

        $when = $period ? ' for ' . CompanyForm::periodLabel($period) : '';
        return back()->with('success', "Form assigned{$when}. {$count} new employee(s) added.");
    }

    /**
     * Open a monthly form for the CURRENT month: create a fresh pending
     * submission for every assigned employee who doesn't have one yet, and
     * notify them. Idempotent — safe to click again or run from the scheduler.
     */
    public function openMonth(CompanyForm $companyForm)
    {
        abort_unless($companyForm->is_monthly, 404);

        $period = $companyForm->currentPeriod();
        $count = 0;

        foreach ($companyForm->getAssignedUsersFor() as $user) {
            $sub = FormSubmission::firstOrCreate(
                ['form_id' => $companyForm->id, 'user_id' => $user->id, 'period' => $period],
                ['status' => 'pending']
            );
            if ($sub->wasRecentlyCreated) {
                $count++;
                try {
                    $user->notify(new FormAssigned($companyForm, $period));
                } catch (\Throwable $e) {
                    // ignore per-user notification failure
                }
            }
        }

        $label = CompanyForm::periodLabel($period);

        return back()->with('success', $count > 0
            ? "Opened “{$companyForm->title}” for {$label} — {$count} employee(s) notified."
            : "“{$companyForm->title}” is already open for {$label} for everyone assigned.");
    }

    /**
     * Remove an assignment from a form. Any still-pending (not yet submitted)
     * submissions for users who are no longer assigned through another rule are
     * cleaned up; already-submitted responses are always kept.
     */
    public function unassign(CompanyForm $companyForm, FormAssignment $assignment)
    {
        abort_unless($assignment->form_id === $companyForm->id, 404);

        $label = $assignment->label;
        $assignment->delete();

        // Whoever is still assigned through any remaining rule keeps their form.
        $stillAssigned = $companyForm->getAssignedUsersFor()->pluck('id')->all();

        $removed = $companyForm->submissions()
            ->where('status', '!=', 'submitted')
            ->whereNotIn('user_id', $stillAssigned ?: [0])
            ->delete();

        return back()->with('success', "Unassigned \"{$label}\". {$removed} pending submission(s) removed.");
    }

    public function show(CompanyForm $companyForm)
    {
        $companyForm->load(['fields', 'assignments', 'submissions.employee']);

        return view('company-forms.show', ['form' => $companyForm] + self::assignTargets());
    }

    public function responses(Request $request, CompanyForm $companyForm)
    {
        abort_unless($companyForm->canBeReviewedBy($request->user()), 403);

        $assigned = $companyForm->getAssignedUsersFor();

        // Monthly forms: filter submissions to a chosen month (default: latest).
        $periods = $companyForm->submissionPeriods();
        $period = null;
        if ($companyForm->is_monthly && $periods) {
            $requested = $request->get('period');
            $period = ($requested === 'all') ? 'all'
                : (in_array($requested, $periods, true) ? $requested : $periods[0]);
        }

        $submissions = $companyForm->submissions()->with('employee.department')
            ->when($period && $period !== 'all', fn ($q) => $q->where('period', $period))
            ->latest('id')->get();

        $stats = [
            'assigned' => $assigned->count(),
            'submitted' => $submissions->where('status', 'submitted')->count(),
            'pending' => $submissions->where('status', '!=', 'submitted')->count(),
            'overdue' => $companyForm->isOverdue() ? $submissions->where('status', '!=', 'submitted')->count() : 0,
        ];

        return view('company-forms.responses', [
            'form' => $companyForm,
            'submissions' => $submissions,
            'stats' => $stats,
            'periods' => $periods,
            'selectedPeriod' => $period,
        ]);
    }

    /**
     * A single "Form Responses" inbox across every form the user may review, so
     * admins can triage, comment on, and approve/reject submissions inline (via
     * a slide-over) without opening each form and each submission one at a time.
     */
    public function inbox(Request $request)
    {
        $user = $request->user();

        $formIds = $user->isAdmin()
            ? CompanyForm::pluck('id')
            : CompanyForm::whereHas('reviewers', fn ($q) => $q->where('users.id', $user->id))->pluck('id');

        abort_if(!$user->isAdmin() && $formIds->isEmpty(), 403);

        $status = $request->get('status', 'awaiting');   // awaiting | approved | rejected | all
        $formId = $request->get('form');
        $search = trim((string) $request->get('q', ''));

        // A fresh "submitted rows for my forms" builder each time it's needed.
        $submitted = fn () => FormSubmission::whereIn('form_id', $formIds)->where('status', 'submitted');
        $onlyAwaiting = fn ($q) => $q->where(fn ($w) => $w->whereNull('review_status')->orWhere('review_status', 'pending'));

        $counts = [
            'awaiting' => $onlyAwaiting($submitted())->count(),
            'approved' => $submitted()->where('review_status', 'approved')->count(),
            'rejected' => $submitted()->where('review_status', 'rejected')->count(),
        ];

        $q = $submitted()->with(['form.fields', 'employee', 'responses', 'reviewer']);
        if ($formId) {
            $q->where('form_id', $formId);
        }
        if ($status === 'awaiting') {
            $onlyAwaiting($q);
        } elseif (in_array($status, ['approved', 'rejected'], true)) {
            $q->where('review_status', $status);
        }
        if ($search !== '') {
            $q->whereHas('employee', fn ($e) => $e->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")));
        }

        $submissions = $q->latest('submitted_at')->latest('id')->paginate(20)->withQueryString();
        $forms = CompanyForm::whereIn('id', $formIds)->orderBy('title')->get(['id', 'title']);

        return view('company-forms.inbox', compact('submissions', 'forms', 'counts', 'status', 'formId', 'search'));
    }

    public function viewSubmission(Request $request, FormSubmission $submission)
    {
        $submission->load(['form.fields', 'employee', 'responses', 'reviewer']);

        // A reviewer/admin gets the review panel; the owner may view their own
        // past submission read-only (form history).
        $canReview = optional($submission->form)->canBeReviewedBy($request->user());
        abort_unless($canReview || $submission->user_id === $request->user()->id, 403);

        return view('company-forms.view-submission', [
            'submission' => $submission,
            'employeeView' => !$canReview,
        ]);
    }

    /** Admin or an assigned reviewer: approve/reject with an optional suggestion; notify the employee. */
    public function reviewSubmission(Request $request, FormSubmission $submission)
    {
        abort_unless(optional($submission->form)->canBeReviewedBy($request->user()), 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => 'nullable|string|max:2000',
        ]);

        $submission->update([
            'review_status' => $validated['action'] === 'approve' ? 'approved' : 'rejected',
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($submission->employee) {
            try {
                $submission->employee->notify(new \App\Notifications\FormReviewed($submission));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $label = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        return back()->with('success', "Response {$label}. The employee has been notified.");
    }

    /** Super admin: grant an employee access to review this form's responses. */
    public function assignReviewer(Request $request, CompanyForm $companyForm)
    {
        abort_unless($request->user() && $request->user()->hasRole('super_admin'), 403);

        $validated = $request->validate(['user_id' => 'required|exists:users,id']);

        $companyForm->reviewers()->syncWithoutDetaching([
            $validated['user_id'] => ['assigned_by' => $request->user()->id],
        ]);

        if ($reviewer = User::find($validated['user_id'])) {
            try {
                $reviewer->notify(new \App\Notifications\FormReviewAccessGranted($companyForm));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Reviewer added — they can now view and review responses.');
    }

    /** Super admin: revoke a reviewer's access. */
    public function removeReviewer(Request $request, CompanyForm $companyForm, User $user)
    {
        abort_unless($request->user() && $request->user()->hasRole('super_admin'), 403);

        $companyForm->reviewers()->detach($user->id);

        return back()->with('success', 'Reviewer removed.');
    }

    /** Landing page for reviewers: the forms they've been given access to review. */
    public function myReviews(Request $request)
    {
        $user = $request->user();

        $forms = $user->isAdmin()
            ? CompanyForm::withCount('submissions')->latest()->get()
            : $user->reviewableForms()->withCount('submissions')->latest()->get();

        return view('company-forms.my-reviews', compact('forms'));
    }

    public function exportResponses(CompanyForm $companyForm)
    {
        $companyForm->load('fields');

        return Excel::download(new FormResponsesExport($companyForm), Str::slug($companyForm->title) . '-responses.xlsx');
    }

    public function destroy(CompanyForm $companyForm)
    {
        $companyForm->delete();

        return redirect()->route('company-forms.index')->with('success', 'Form archived — you can restore it anytime from the Archive.');
    }

    // ---------------------------------------------------------------------

    private function uniqueKey(CompanyForm $form, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base;
        $i = 1;
        $existing = $form->fields()->pluck('field_key')->all();
        while (in_array($key, $existing, true)) {
            $key = $base . '_' . (++$i);
        }

        return $key;
    }

    /** Data needed by the assign panel. */
    public static function assignTargets(): array
    {
        return [
            'users' => User::where('account_status', '!=', 'deactivated')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ];
    }
}
