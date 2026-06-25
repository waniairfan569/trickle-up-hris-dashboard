<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Notifications\DocumentSignatureRequested;
use App\Notifications\DocumentSigningCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentRequestController extends Controller
{
    /** GET document-templates/{tpl}/send — pick a subject employee (admin). */
    public function sendForm(DocumentTemplate $documentTemplate)
    {
        abort_unless($documentTemplate->isPdf(), 422, 'Signing is available for PDF templates only.');
        $documentTemplate->load('signers');

        $entityIds = $documentTemplate->entities()->pluck('company_entities.id')->all();
        $deptIds = $documentTemplate->departments()->pluck('departments.id')->all();

        $employees = User::where('account_status', '!=', 'deactivated')
            ->when($entityIds, fn ($q) => $q->whereIn('company_entity_id', $entityIds))
            ->when($deptIds, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'job_title']);

        // Always let the current admin send a document to themselves, even if they
        // fall outside the template's entity/department scope.
        $me = auth()->user();
        if ($me && !$employees->contains('id', $me->id)) {
            $employees->push(User::select('id', 'first_name', 'last_name', 'job_title')->find($me->id));
            $employees = $employees->sortBy(fn ($u) => $u->last_name . ' ' . $u->first_name)->values();
        }

        return view('documents.send', compact('documentTemplate', 'employees'));
    }

    /** POST document-templates/{tpl}/send — create a request + resolve signers (admin). */
    public function send(Request $request, DocumentTemplate $documentTemplate)
    {
        abort_unless($documentTemplate->isPdf(), 422, 'Signing is available for PDF templates only.');
        $request->validate(['employee' => 'required|integer|exists:users,id']);

        $subject = User::findOrFail($request->integer('employee'));
        $documentTemplate->load('signers.employee');

        if ($documentTemplate->signers->isEmpty()) {
            return back()->withErrors(['employee' => 'This template has no signers configured. Edit the template and add at least one signer.']);
        }

        // Resolve each template signer into an actual user.
        $resolved = [];
        foreach ($documentTemplate->signers as $s) {
            if ($s->signer_type === 'employee') {
                $resolved[] = ['user' => $s->employee, 'role' => 'specific'];
            } elseif ($s->role === 'employee') {
                $resolved[] = ['user' => $subject, 'role' => 'employee'];
            } elseif ($s->role === 'line_manager') {
                $resolved[] = ['user' => $subject->manager, 'role' => 'line_manager'];
            } elseif (in_array($s->role, ['hr_admin', 'me_now', 'sender'], true)) {
                // HR admin / Me (now) / Sender all resolve to the person sending the document.
                $resolved[] = ['user' => auth()->user(), 'role' => $s->role];
            }
        }

        foreach ($resolved as $r) {
            if (!$r['user']) {
                $what = $r['role'] === 'line_manager'
                    ? "{$subject->first_name} has no line manager assigned"
                    : 'a signer could not be resolved';

                return back()->withErrors(['employee' => "Cannot send: {$what}."]);
            }
        }

        $docRequest = DB::transaction(function () use ($documentTemplate, $subject, $resolved, $request) {
            $docRequest = DocumentRequest::create([
                'document_template_id' => $documentTemplate->id,
                'subject_employee_id' => $subject->id,
                'created_by' => auth()->user()->id,
                'status' => 'in_progress',
                'current_position' => 0,
            ]);

            foreach (array_values($resolved) as $i => $r) {
                $docRequest->signers()->create([
                    'position' => $i,
                    'user_id' => $r['user']->id,
                    'source_role' => $r['role'],
                    'status' => 'pending',
                ]);
            }

            $docRequest->logEvent('created', auth()->user(), "Sent to {$subject->full_name}", $request->ip());

            return $docRequest;
        });

        // Notify the first signer.
        $first = $docRequest->signers()->orderBy('position')->first();
        $first->user?->notify(new DocumentSignatureRequested($docRequest));

        return redirect()->route('documents.show', $docRequest)
            ->with('success', "“{$documentTemplate->name}” sent to {$subject->full_name} for signature.");
    }

    /** GET documents — inbox of requests I must sign / am involved in. */
    public function index(Request $request)
    {
        $user = auth()->user();

        $all = DocumentRequest::with(['template', 'subject', 'signers.user'])
            ->when(!$user->isAdmin(), function ($q) use ($user) {
                $q->where(function ($w) use ($user) {
                    $w->where('subject_employee_id', $user->id)
                        ->orWhere('created_by', $user->id)
                        ->orWhereHas('signers', fn ($s) => $s->where('user_id', $user->id));
                });
            })
            ->latest()
            ->get();

        $toSign = $all->filter(fn ($r) => $r->isAwaiting($user))->values();

        return view('documents.index', compact('toSign', 'all'));
    }

    /** GET documents/{request} — request detail + audit trail. */
    public function show(DocumentRequest $documentRequest)
    {
        $this->authorizeView($documentRequest);
        $documentRequest->load(['template', 'subject', 'creator', 'signers.user', 'events.user']);

        return view('documents.show', compact('documentRequest'));
    }

    /** GET documents/{request}/sign — signing page (current signer only). */
    public function sign(Request $request, DocumentRequest $documentRequest)
    {
        $user = auth()->user();
        abort_unless($documentRequest->isAwaiting($user), 403, 'This document is not awaiting your signature.');

        $documentRequest->load(['template.fields', 'subject']);
        $signer = $documentRequest->currentSigner();
        $documentRequest->logEvent('viewed', $user, null, $request->ip());

        // Signature/initials boxes for this signer. With a single signer, show every
        // box (they sign the whole document); with multiple, match by party.
        $signerCount = $documentRequest->signers()->count();
        $myParty = $this->partyOf($signer->source_role);
        $myBoxes = $documentRequest->template->fields
            ->filter(fn ($f) => $f->page && in_array($f->field_type, ['signature', 'initials'], true)
                && ($signerCount <= 1 || $this->partyOf($f->assignee) === $myParty))
            ->values();

        $tokens = $this->resolveTokens($documentRequest->subject);

        return view('documents.sign', compact('documentRequest', 'signer', 'myBoxes', 'tokens'));
    }

    /** POST documents/{request}/sign — store this signer's signature, advance the flow. */
    public function storeSignature(Request $request, DocumentRequest $documentRequest)
    {
        $user = auth()->user();
        abort_unless($documentRequest->isAwaiting($user), 403, 'This document is not awaiting your signature.');

        $request->validate(['signature' => 'required|string']);

        $signer = $documentRequest->currentSigner();
        $signer->update([
            'status' => 'signed',
            'signature_image' => $request->input('signature'),
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
        ]);
        $documentRequest->logEvent('signed', $user, $signer->role_label, $request->ip());

        $next = $documentRequest->signers()->where('status', 'pending')->orderBy('position')->first();
        if ($next) {
            $documentRequest->update(['current_position' => $next->position]);
            $next->user?->notify(new DocumentSignatureRequested($documentRequest));

            return redirect()->route('documents.show', $documentRequest)->with('success', 'Signed. The next signer has been notified.');
        }

        $documentRequest->update(['status' => 'completed', 'completed_at' => now()]);
        $documentRequest->logEvent('completed', $user, null, $request->ip());

        // Notify subject + creator that signing is complete.
        foreach (array_filter([$documentRequest->subject, $documentRequest->creator]) as $person) {
            $person->notify(new DocumentSigningCompleted($documentRequest));
        }

        return redirect()->route('documents.show', $documentRequest)->with('success', 'Document fully signed and completed.');
    }

    /** POST documents/{request}/decline — current signer declines. */
    public function decline(Request $request, DocumentRequest $documentRequest)
    {
        $user = auth()->user();
        abort_unless($documentRequest->isAwaiting($user), 403, 'This document is not awaiting your signature.');

        $signer = $documentRequest->currentSigner();
        $signer->update(['status' => 'declined', 'signed_at' => now(), 'signed_ip' => $request->ip()]);
        $documentRequest->update(['status' => 'declined']);
        $documentRequest->logEvent('declined', $user, $request->input('reason'), $request->ip());

        $documentRequest->creator?->notify(new DocumentSigningCompleted($documentRequest));

        return redirect()->route('documents.show', $documentRequest)->with('success', 'You declined to sign this document.');
    }

    /** GET documents/{request}/file — stream the template file for participants. */
    public function file(DocumentRequest $documentRequest)
    {
        $this->authorizeView($documentRequest);
        $template = $documentRequest->template;
        if (!$template || !$template->file_path || !Storage::exists($template->file_path)) {
            abort(404, 'File not found.');
        }

        $headers = $template->isPdf() ? ['Content-Type' => 'application/pdf'] : [];

        return Storage::response($template->file_path, $template->file_name, $headers);
    }

    /** GET documents/{request}/signed-data — JSON of values + signatures for the final PDF. */
    public function signedData(DocumentRequest $documentRequest)
    {
        $this->authorizeView($documentRequest);
        $documentRequest->load(['template.fields', 'subject', 'signers.user']);
        $subject = $documentRequest->subject;

        // Latest signature image + signer name per party (employee / sender_party / line_manager).
        $sigByParty = [];
        $nameByParty = [];
        foreach ($documentRequest->signers as $s) {
            if ($s->signature_image) {
                $party = $this->partyOf($s->source_role);
                $sigByParty[$party] = $s->signature_image;
                $nameByParty[$party] = optional($s->user)->full_name;
            }
        }

        // Single-signer fallback: one person signs the whole document, so their
        // signature/name fills every signature box regardless of assignee.
        $signed = $documentRequest->signers->filter(fn ($s) => $s->signature_image)->values();
        $fallbackSig = $signed->count() === 1 ? $signed[0]->signature_image : null;
        $fallbackName = $signed->count() === 1 ? optional($signed[0]->user)->full_name : null;

        $fields = $documentRequest->template->fields
            ->filter(fn ($f) => $f->page)
            ->map(function ($f) use ($subject, $sigByParty, $nameByParty, $fallbackSig, $fallbackName) {
                $value = '';
                $image = null;
                $name = null;
                if ($f->field_type === 'text' && $f->field_key) {
                    $value = (string) ($subject->getFieldValue($f->field_key) ?? '');
                } elseif (in_array($f->field_type, ['signature', 'initials'], true)) {
                    $party = $this->partyOf($f->assignee);
                    $image = $sigByParty[$party] ?? $fallbackSig;
                    $name = $nameByParty[$party] ?? $fallbackName;
                }

                return [
                    'label' => $f->label,
                    'type' => $f->field_type,
                    'value' => $value,
                    'image' => $image,
                    'name' => $name,
                    'page' => (int) $f->page,
                    'x' => (float) $f->pos_x,
                    'y' => (float) $f->pos_y,
                    'w' => (float) $f->width,
                    'h' => (float) $f->height,
                ];
            })->values();

        return response()->json([
            'fileUrl' => route('documents.file', $documentRequest),
            'fileName' => pathinfo($documentRequest->template->file_name, PATHINFO_FILENAME) . ' — signed',
            'fields' => $fields,
            'tokens' => $this->resolveTokens($subject),
        ]);
    }

    // ---------------------------------------------------------------------

    /**
     * Map bracketed template tokens (typed literally into the PDF) to the
     * subject's real values, so the UI/PDF can overlay them, e.g.
     * ['[candidate]' => 'Jane Doe', '[job]' => 'Designer', ...].
     */
    private function resolveTokens($subject): array
    {
        if (!$subject) {
            return [];
        }

        $keys = [
            'candidate', 'employee', 'employee_name', 'job', 'position', 'designation',
            'today_date', 'date', 'agreement_date', 'start_date', 'commencement_date',
            'date_of_commencement', 'department', 'email',
        ];

        $tokens = [];
        foreach ($keys as $k) {
            $v = $subject->getFieldValue($k);
            if ($v !== null && $v !== '') {
                $tokens['[' . $k . ']'] = (string) $v;
            }
        }

        return $tokens;
    }

    /**
     * Normalize a field assignee OR a signer role into a "party" so signature boxes
     * match the right signer. HR admin / Me (now) / Sender are all the sending party.
     */
    private function partyOf(string $value): string
    {
        return match ($value) {
            'employee' => 'employee',
            'line_manager' => 'line_manager',
            'hr_admin', 'me_now', 'sender' => 'sender_party',
            default => $value,
        };
    }

    /** Only participants (subject, creator, a signer) or admins may view. */
    private function authorizeView(DocumentRequest $documentRequest): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        $isParticipant = $documentRequest->subject_employee_id === $user->id
            || $documentRequest->created_by === $user->id
            || $documentRequest->signers()->where('user_id', $user->id)->exists();

        abort_unless($isParticipant, 403, 'You do not have access to this document.');
    }
}
