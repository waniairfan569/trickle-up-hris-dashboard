<?php

namespace App\Http\Controllers;

use App\Models\CompanyForm;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FormSubmissionController extends Controller
{
    /** Employee: list forms assigned to me. */
    public function myForms(Request $request)
    {
        $user = $request->user();
        $submissions = $user->formSubmissions()
            ->with(['form', 'reviewer'])
            ->get()
            ->filter(fn ($s) => $s->form && $s->form->status !== 'draft')
            ->sortByDesc('id')
            ->values();

        // Seeing the list clears the "new forms" sidebar badge.
        $user->forceFill(['forms_last_seen_at' => now()])->saveQuietly();

        return view('employee.forms.index', compact('submissions'));
    }

    /** Employee: open a form to fill (or view if already submitted & single-submission). */
    public function fill(Request $request, CompanyForm $companyForm)
    {
        $user = $request->user();
        abort_if($companyForm->status === 'draft', 404);
        $this->authorizeAssigned($companyForm, $user);

        $submission = $companyForm->getSubmissionFor($user);

        // A single-submission form that's already done → read-only view.
        if ($submission && $submission->status === 'submitted' && !$companyForm->allow_multiple_submissions) {
            $submission->load('responses');
            return view('company-forms.view-submission', ['submission' => $submission, 'employeeView' => true]);
        }

        // Continue an open draft, or start a fresh blank one. "Submit again" on a
        // multi-submission form lands here with the last one already submitted,
        // so a NEW blank submission is created (previous ones stay as history).
        $submission = $this->draftFor($companyForm, $user);

        $companyForm->load('fields');
        $existing = $submission->responses()->pluck('value', 'field_key')->all();

        return view('employee.forms.fill', ['form' => $companyForm, 'submission' => $submission, 'existing' => $existing]);
    }

    /** Employee: save progress (AJAX) without final submit. */
    public function save(Request $request, CompanyForm $companyForm)
    {
        $user = $request->user();
        $this->authorizeAssigned($companyForm, $user);

        $submission = $this->draftFor($companyForm, $user);

        $this->persistResponses($companyForm, $submission, $request, false);

        return response()->json(['saved' => true, 'progress' => $submission->fresh()->progressPercent()]);
    }

    /** Employee: final submit with validation. */
    public function submit(Request $request, CompanyForm $companyForm)
    {
        $user = $request->user();
        abort_if($companyForm->status === 'draft', 404);
        $this->authorizeAssigned($companyForm, $user);
        $companyForm->load('fields');

        // Validate required input fields.
        $rules = [];
        $attributes = [];
        foreach ($companyForm->fields as $field) {
            if (!$field->isInputField() || !$field->is_required) {
                continue;
            }
            $key = 'fields.' . $field->field_key;
            $rules[$key] = $field->type === 'multi_select' ? 'required|array|min:1' : 'required';
            $attributes[$key] = $field->label;
        }
        if ($companyForm->requires_signature) {
            $rules['signature_data'] = 'required|string';
            $attributes['signature_data'] = 'signature';
        }
        $request->validate($rules, [], $attributes);

        $submission = $this->draftFor($companyForm, $user);

        $this->persistResponses($companyForm, $submission, $request, true);

        $submission->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'signature_data' => $companyForm->requires_signature ? $request->input('signature_data') : $submission->signature_data,
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->notifyAdminsOfSubmission($companyForm, $user);
        } catch (\Throwable $e) {
            report($e); // never let a notification problem fail a saved submission
        }

        return redirect()->route('my-forms.index')->with('success', 'Form submitted. Thank you!');
    }

    /** Download an uploaded file response (owner or admin). */
    public function downloadFile(Request $request, \App\Models\FormResponse $response)
    {
        $submission = $response->submission;
        $user = $request->user();
        abort_unless($submission && ($user->isAdmin() || $submission->user_id === $user->id), 403);
        abort_unless($response->isFile() && Storage::exists($response->value), 404);

        return Storage::download($response->value);
    }

    // ---------------------------------------------------------------------

    /**
     * The employee's active (not-yet-submitted) draft for a form. Continues an
     * open draft if one exists; otherwise starts a fresh blank submission —
     * never reuses an already-submitted one. This keeps every past submission
     * as history and makes "Submit again" open a clean, empty form.
     */
    private function draftFor(CompanyForm $form, User $user): FormSubmission
    {
        $latest = $form->getSubmissionFor($user);

        if ($latest && $latest->status !== 'submitted') {
            if ($latest->status === 'pending') {
                $latest->update(['status' => 'in_progress', 'started_at' => $latest->started_at ?? now()]);
            }
            return $latest;
        }

        return FormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    private function persistResponses(CompanyForm $form, FormSubmission $submission, Request $request, bool $isSubmit): void
    {
        $form->loadMissing('fields');
        $input = $request->input('fields', []);

        foreach ($form->fields as $field) {
            if (!$field->isInputField()) {
                continue;
            }

            $value = null;

            if ($field->type === 'file_upload') {
                if ($request->hasFile("fields.{$field->field_key}")) {
                    $value = $request->file("fields.{$field->field_key}")
                        ->store(\App\Tenancy\TenantStorage::path("form-uploads/{$form->id}/{$submission->user_id}"));
                } else {
                    // keep existing file value if present
                    $value = optional($submission->responses()->where('field_key', $field->field_key)->first())->value;
                }
            } elseif (in_array($field->type, ['multi_select'], true)) {
                $arr = $input[$field->field_key] ?? [];
                $value = is_array($arr) && count($arr) ? json_encode(array_values($arr)) : null;
            } else {
                $value = $input[$field->field_key] ?? null;
            }

            if ($value === null && !$isSubmit) {
                continue; // don't overwrite on autosave with empties
            }

            $submission->responses()->updateOrCreate(
                ['field_id' => $field->id],
                ['field_key' => $field->field_key, 'field_label' => $field->label, 'value' => $value]
            );
        }
    }

    /** Notify the form creator + HR admins that a response came in. */
    private function notifyAdminsOfSubmission(CompanyForm $form, $submitter): void
    {
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->where('account_status', '!=', 'deactivated')
            ->get();

        if ($form->created_by) {
            $creator = User::find($form->created_by);
            if ($creator && !$recipients->contains('id', $creator->id)) {
                $recipients->push($creator);
            }
        }

        $name = $form->is_anonymous ? null : trim($submitter->first_name . ' ' . $submitter->last_name);

        foreach ($recipients->where('id', '!=', $submitter->id) as $recipient) {
            try {
                $recipient->notify(new \App\Notifications\FormSubmitted($form, $name));
            } catch (\Throwable $e) {
            }
        }
    }

    private function authorizeAssigned(CompanyForm $form, $user): void
    {
        if ($user->isAdmin()) {
            return;
        }
        $assigned = $form->getAssignedUsersFor()->pluck('id')->contains($user->id)
            || $form->submissions()->where('user_id', $user->id)->exists();
        abort_unless($assigned, 403, 'This form is not assigned to you.');
    }
}
