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

        // Existing signer choices (pre-fill the picker), and the backing company
        // document so Access & Settings live on this "Send for signature" page.
        $signerChoices = $documentTemplate->signers->map(fn ($s) => $s->signer_type === 'employee'
            ? 'emp:' . $s->employee_id
            : 'role:' . ($s->role ?: 'employee'))->values();

        $companyDoc = \App\Models\CompanyDocument::where('template_id', $documentTemplate->id)
            ->with('accessRecords')->first();
        $departments = \App\Models\Department::orderBy('name')->get(['id', 'name']);
        $accessUsers = User::where('account_status', '!=', 'deactivated')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('documents.send', compact(
            'documentTemplate', 'employees', 'signerChoices', 'companyDoc', 'departments', 'accessUsers'
        ));
    }

    /** POST document-templates/{tpl}/send — create a request + resolve signers (admin). */
    public function send(Request $request, DocumentTemplate $documentTemplate)
    {
        abort_unless($documentTemplate->isPdf(), 422, 'Signing is available for PDF templates only.');
        $request->validate([
            'employee' => 'required|array|min:1',
            'employee.*' => 'integer|exists:users,id',
        ]);

        // Signers are chosen on this page (after preview) — rebuild them in order.
        if ($request->has('signers')) {
            DB::transaction(function () use ($documentTemplate, $request) {
                $documentTemplate->signers()->delete();
                foreach (array_values($request->input('signers', [])) as $i => $signer) {
                    $type = ($signer['signer_type'] ?? 'role') === 'employee' ? 'employee' : 'role';
                    $documentTemplate->signers()->create([
                        'position' => $i,
                        'signer_type' => $type,
                        'role' => $type === 'role' ? ($signer['role'] ?? 'employee') : null,
                        'employee_id' => $type === 'employee' ? (($signer['employee_id'] ?? null) ?: null) : null,
                    ]);
                }
            });
        }

        // Access & settings for the backing company document live here too.
        $companyDoc = \App\Models\CompanyDocument::where('template_id', $documentTemplate->id)->first();
        if ($companyDoc && $request->has('access_level')) {
            $companyDoc->access_level = $request->input('access_level') ?: 'company_wide';
            $companyDoc->requires_acknowledgment = $request->boolean('requires_acknowledgment');
            $companyDoc->is_active = $request->boolean('is_active', true);
            $companyDoc->expires_at = $request->input('expires_at') ?: null;
            $companyDoc->save();

            $companyDoc->accessRecords()->delete();
            if ($companyDoc->access_level === 'department') {
                foreach ((array) $request->input('departments', []) as $id) {
                    $companyDoc->accessRecords()->create(['access_type' => 'department', 'access_id' => $id]);
                }
            } elseif ($companyDoc->access_level === 'specific_users') {
                foreach ((array) $request->input('users', []) as $id) {
                    $companyDoc->accessRecords()->create(['access_type' => 'user', 'access_id' => $id]);
                }
            }
        }

        $documentTemplate->load('signers.employee');

        if ($documentTemplate->signers->isEmpty()) {
            return back()->withInput()->withErrors(['signers' => 'Please add at least one signer before sending.']);
        }

        $subjects = User::whereIn('id', $request->input('employee', []))->get();
        if ($subjects->isEmpty()) {
            return back()->withErrors(['employee' => 'Pick at least one employee to send to.']);
        }

        $sentNames = [];
        $skipped = [];
        $lastRequest = null;

        // Each chosen employee gets their OWN signature request (signers resolved
        // relative to that person).
        foreach ($subjects as $subject) {
            $resolved = [];
            foreach ($documentTemplate->signers as $s) {
                if ($s->signer_type === 'employee') {
                    $resolved[] = ['user' => $s->employee, 'role' => 'specific'];
                } elseif ($s->role === 'employee') {
                    $resolved[] = ['user' => $subject, 'role' => 'employee'];
                } elseif ($s->role === 'line_manager') {
                    $resolved[] = ['user' => $subject->manager, 'role' => 'line_manager'];
                } elseif (in_array($s->role, ['hr_admin', 'me_now', 'sender'], true)) {
                    // HR admin / Me (now) / Sender all resolve to the person sending.
                    $resolved[] = ['user' => auth()->user(), 'role' => $s->role];
                }
            }

            // Can't resolve a signer for this person (e.g. no line manager) → skip them.
            if (collect($resolved)->contains(fn ($r) => empty($r['user']))) {
                $skipped[] = $subject->full_name;
                continue;
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

            $docRequest->signers()->orderBy('position')->first()?->user?->notify(new DocumentSignatureRequested($docRequest));
            $sentNames[] = $subject->full_name;
            $lastRequest = $docRequest;
        }

        if (empty($sentNames)) {
            return back()->withErrors(['employee' => 'Could not send — ' . implode(', ', $skipped) . ' have no line manager assigned.']);
        }

        $msg = count($sentNames) === 1
            ? "“{$documentTemplate->name}” sent to {$sentNames[0]} for signature."
            : "“{$documentTemplate->name}” sent to " . count($sentNames) . ' employees for signature.';
        if (!empty($skipped)) {
            $msg .= ' Skipped ' . implode(', ', $skipped) . ' (no line manager).';
        }

        // One recipient → open that request; several → back to the documents list.
        if (count($sentNames) === 1 && $lastRequest) {
            return redirect()->route('documents.show', $lastRequest)->with('success', $msg);
        }

        return redirect()->route('company-documents.admin')->with('success', $msg);
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

        // No signature box was placed for this signer — the client will drop a
        // "Sign here" box. If the document contains a signature TOKEN for this
        // signer's party (e.g. [Employee Signature]) the client places the box
        // AT the token; otherwise it falls back to the bottom of the last page.
        $autoSignLastPage = $myBoxes->isEmpty();
        $sigSpellings = $this->signatureTokenSpellings()[$myParty] ?? [];

        // Placed text fields, pre-filled from the signer's profile, so the
        // signing screen matches what will be flattened into the final PDF.
        $subject = $documentRequest->subject;
        $filledFields = $documentRequest->template->fields
            ->filter(fn ($f) => $f->page && $f->field_type === 'text' && $f->field_key)
            ->map(fn ($f) => [
                'page' => (int) $f->page, 'x' => (float) $f->pos_x, 'y' => (float) $f->pos_y,
                'w' => (float) $f->width, 'h' => (float) $f->height,
                'value' => (string) (optional($subject)->getFieldValue($f->field_key) ?? ''),
            ])
            ->filter(fn ($f) => $f['value'] !== '')
            ->values();

        $tokens = $this->resolveTokens($documentRequest->subject);
        $tokens = array_merge($tokens, $this->signatureBlockTokens($documentRequest));
        // Values employees already typed (bracket-token => value) so re-opens
        // and later signers see the filled contract, not raw placeholders.
        $tokens = array_merge($tokens, $this->savedFieldValues($documentRequest));

        // Every signature-token spelling (all parties) so the client never
        // mistakes a signature placeholder for an employee-fillable field.
        $allSigSpellings = \Illuminate\Support\Arr::flatten($this->signatureTokenSpellings());

        // Only the person the document is FOR (or a sole signer) is offered the
        // fill-in form; other signers just see whatever the employee entered.
        $canFillFields = $signerCount <= 1
            || (int) $signer->user_id === (int) $documentRequest->subject_employee_id;

        // Saved signature templates the signer can reuse instead of drawing.
        $savedSignatures = \App\Models\SignatureTemplate::latest()->get(['id', 'name', 'image_data']);

        return view('documents.sign', compact('documentRequest', 'signer', 'myBoxes', 'tokens', 'autoSignLastPage', 'filledFields', 'sigSpellings', 'savedSignatures', 'allSigSpellings', 'canFillFields'));
    }

    /** Merge every signer's employee-entered field values into one map. */
    private function savedFieldValues(DocumentRequest $documentRequest): array
    {
        $documentRequest->loadMissing('signers');
        $out = [];
        foreach ($documentRequest->signers as $s) {
            foreach ((array) $s->field_values as $token => $value) {
                if (is_string($token) && $value !== null && $value !== '') {
                    $out[$token] = (string) $value;
                }
            }
        }
        return $out;
    }

    /** Signature-token spellings per party (regardless of signed state). */
    private function signatureTokenSpellings(): array
    {
        return [
            'employee' => ['[candidate_signature]', '[Employee Signature]', "[Employee's Signature]", '[Candidate Signature]'],
            'sender_party' => ['[company_signature]', '[Company Signature]', '[Sender Signature]', "[Sender's Signature]", "[Sender's signature]", '[Authorised Signature]', '[Authorized Signature]'],
        ];
    }

    /** POST documents/{request}/sign — store this signer's signature, advance the flow. */
    public function storeSignature(Request $request, DocumentRequest $documentRequest)
    {
        $user = auth()->user();
        abort_unless($documentRequest->isAwaiting($user), 403, 'This document is not awaiting your signature.');

        $request->validate([
            'signature' => 'required|string',
            'employee_fields' => 'nullable|string',
        ]);

        // Employee-filled values (bracket-token => typed value), posted as JSON.
        $fieldValues = [];
        if ($raw = $request->input('employee_fields')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $token => $value) {
                    if (is_string($token) && (is_string($value) || is_numeric($value))) {
                        $fieldValues[$token] = trim((string) $value);
                    }
                }
            }
        }

        $signer = $documentRequest->currentSigner();
        $signer->update([
            'status' => 'signed',
            'signature_image' => $request->input('signature'),
            'field_values' => $fieldValues ?: null,
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
        $documentRequest->load(['template.fields.signatureTemplate', 'subject', 'signers.user']);
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
                $type = $f->field_type;
                if ($f->field_type === 'text' && $f->field_key) {
                    $value = (string) ($subject->getFieldValue($f->field_key) ?? '');
                } elseif ($f->field_type === 'saved_signature') {
                    // Auto-stamped saved signature — rendered like a signature image.
                    $type = 'signature';
                    $image = optional($f->signatureTemplate)->image_data;
                } elseif (in_array($f->field_type, ['signature', 'initials'], true)) {
                    $party = $this->partyOf($f->assignee);
                    $image = $sigByParty[$party] ?? $fallbackSig;
                    $name = $nameByParty[$party] ?? $fallbackName;
                }

                return [
                    'label' => $f->label,
                    'type' => $type,
                    'value' => $value,
                    'image' => $image,
                    'name' => $name,
                    'page' => (int) $f->page,
                    'x' => (float) $f->pos_x,
                    'y' => (float) $f->pos_y,
                    'w' => (float) $f->width,
                    'h' => (float) $f->height,
                ];
            })->values()->all();

        // If the template has NO placed signature boxes, stamp each signer's
        // signature at the bottom of the last page (stacked), matching the
        // "Sign here" fallback shown on the signing page.
        $hasPlacedSig = $documentRequest->template->fields
            ->contains(fn ($f) => in_array($f->field_type, ['signature', 'initials', 'saved_signature'], true));
        if (!$hasPlacedSig) {
            $stack = 0;
            foreach ($documentRequest->signers as $s) {
                if (!$s->signature_image) {
                    continue;
                }
                $fields[] = [
                    'label' => 'Signature', 'type' => 'signature', 'value' => '',
                    'image' => $s->signature_image, 'name' => optional($s->user)->full_name,
                    // lastPage marker: the client stamps these on the ACTUAL last page.
                    'page' => 1, 'lastPage' => true, 'x' => 0.08, 'y' => max(0.04, 0.86 - ($stack * 0.09)),
                    'w' => 0.34, 'h' => 0.06,
                ];
                $stack++;
            }
        }

        // Signature tokens that stamp the actual signature IMAGE, and the text
        // tokens (names/values). Remove any spelling we can stamp as an image
        // from the text set so it isn't drawn twice (image + name).
        $signatureTokens = $this->signatureImageTokens($documentRequest);
        $textTokens = array_merge($this->resolveTokens($subject), $this->signatureBlockTokens($documentRequest));
        // Employee-typed values (loan amount, instalments, …) bake in like any
        // other token, on top of profile values.
        $textTokens = array_merge($textTokens, $this->savedFieldValues($documentRequest));
        foreach (array_keys($signatureTokens) as $k) {
            unset($textTokens[$k]);
        }

        return response()->json([
            'fileUrl' => route('documents.file', $documentRequest),
            'fileName' => pathinfo($documentRequest->template->file_name, PATHINFO_FILENAME) . ' — signed',
            'fields' => $fields,
            'tokens' => $textTokens,
            'signatureTokens' => $signatureTokens,
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
        return app(\App\Services\DocumentTokenService::class)->profileTokens($subject);
    }

    /**
     * Resolve the signature-block placeholders (sender name, employee name,
     * and each side's sign date) so the block shows real values instead of
     * raw [sender_name] / [candidate_signature] / [company_sign_date] tokens.
     * Empty strings are returned for not-yet-known values so the raw token is
     * still hidden rather than left showing on the page.
     */
    private function signatureBlockTokens(DocumentRequest $documentRequest): array
    {
        $documentRequest->loadMissing('signers.user', 'creator', 'subject');

        $senderSigner = $documentRequest->signers
            ->first(fn ($s) => $this->partyOf($s->source_role) === 'sender_party');
        $employeeSigner = $documentRequest->signers
            ->first(fn ($s) => $this->partyOf($s->source_role) === 'employee');

        $senderName = optional($senderSigner?->user)->full_name
            ?? optional($documentRequest->creator)->full_name
            ?? '';
        $employeeName = optional($documentRequest->subject)->full_name
            ?? optional($employeeSigner?->user)->full_name
            ?? '';

        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '';

        return [
            // Sender / company signature line (typed-name style).
            '[sender_name]'          => $senderName,
            '[company_signature]'    => $senderName,
            '[Company Signature]'    => $senderName,
            '[Sender Signature]'     => $senderName,
            "[Sender's Signature]"   => $senderName,
            "[Sender's signature]"   => $senderName,
            '[Authorised Signature]' => $senderName,
            '[Authorized Signature]' => $senderName,
            '[company_sign_date]'    => $fmt($senderSigner?->signed_at),
            // Employee signature line (typed-name style).
            '[candidate_signature]'  => $employeeName,
            '[Employee Signature]'   => $employeeName,
            "[Employee's Signature]" => $employeeName,
            '[Candidate Signature]'  => $employeeName,
            '[candidate_sign_date]'  => $fmt($employeeSigner?->signed_at) ?: now()->format('d M Y'),
        ];
    }

    /**
     * Map signature TOKENS (typed literally into the document, e.g.
     * [Employee Signature] / [Sender's signature]) to the matching party's actual
     * signature image, so the token position is stamped with the drawn/saved
     * signature — no placed field needed. Only tokens whose party has already
     * signed are returned; the rest fall back to the name (text) tokens.
     */
    private function signatureImageTokens(DocumentRequest $documentRequest): array
    {
        $documentRequest->loadMissing('signers');

        $imgFor = fn ($party) => optional($documentRequest->signers
            ->first(fn ($s) => $s->signature_image && $this->partyOf($s->source_role) === $party))
            ->signature_image;

        // Single-signer document: that one signature fills every signature token.
        $signed = $documentRequest->signers->filter(fn ($s) => $s->signature_image)->values();
        $fallback = $signed->count() === 1 ? $signed[0]->signature_image : null;

        $employeeImg = $imgFor('employee') ?: $fallback;
        $senderImg   = $imgFor('sender_party') ?: $fallback;

        $map = [];
        if ($employeeImg) {
            foreach (['[candidate_signature]', '[Employee Signature]', "[Employee's Signature]", '[Candidate Signature]'] as $t) {
                $map[$t] = $employeeImg;
            }
        }
        if ($senderImg) {
            foreach (['[company_signature]', '[Company Signature]', '[Sender Signature]', "[Sender's Signature]", "[Sender's signature]", '[Authorised Signature]', '[Authorized Signature]'] as $t) {
                $map[$t] = $senderImg;
            }
        }

        return $map;
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
