<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use App\Models\Department;
use App\Models\DocumentAccess;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyDocumentController extends Controller
{
    private const MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,zip';

    // ----- Admin -----------------------------------------------------------

    public function adminIndex(Request $request)
    {
        $query = CompanyDocument::with(['category', 'uploader', 'template.requests.signers', 'template.requests.subject'])->withCount('acknowledgments')->latest();

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('access')) {
            $query->where('access_level', $request->access);
        }
        if ($request->filled('type')) {
            $query->where('file_extension', $request->type);
        }

        $documents = $query->get();
        $categories = DocumentCategory::orderBy('sort_order')->withCount('documents')->get();

        $stats = [
            'total' => CompanyDocument::count(),
            'downloads_month' => DocumentView::where('action', 'download')->where('created_at', '>=', now()->startOfMonth())->count(),
            'expiring' => CompanyDocument::whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays(30)])->count(),
            'categories' => $categories->count(),
        ];

        return view('company-documents.index', compact('documents', 'categories', 'stats'));
    }

    public function create()
    {
        return view('company-documents.create', [
            'document' => new CompanyDocument(['version' => '1.0', 'access_level' => 'company_wide', 'is_active' => true]),
            'categories' => DocumentCategory::orderBy('sort_order')->get(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'users' => User::where('account_status', '!=', 'deactivated')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateDocument($request, true);
        $category = DocumentCategory::findOrFail($data['category_id']);

        $document = new CompanyDocument($this->payload($request, $data));
        $document->uploaded_by = auth()->id();
        $this->storeFile($document, $request, $category);

        // Word (.doc/.docx) → open in the browser editor first, so the text can
        // be changed / tokens typed in, THEN converted to PDF on "Convert".
        if (in_array($document->file_extension, ['doc', 'docx'], true)) {
            $conversion = app(\App\Services\DocumentConversionService::class);

            $html = $conversion->toEditableHtml($document->file_path);
            if ($html !== null) {
                $document->save();
                $this->syncAccess($document, $request);
                Storage::put($this->editHtmlPath($document), $html);

                return redirect()->route('company-documents.edit-content', $document)
                    ->with('success', 'Document uploaded — edit the text below, then click “Convert to PDF”.');
            }

            // Fallback: straight docx → PDF (no editing step) if the HTML
            // route failed but plain conversion works.
            $pdfPath = $conversion->toPdf($document->file_path);
            if (!$pdfPath) {
                Storage::delete($document->file_path);
                return back()->withInput()->withErrors([
                    'file' => 'Word-to-PDF conversion isn’t available on this server yet. Please upload a PDF, or ask your admin to install LibreOffice.',
                ]);
            }
            Storage::delete($document->file_path); // keep only the PDF
            $document->file_path = $pdfPath;
            $document->file_name = preg_replace('/\.[^.]+$/', '.pdf', $document->file_name);
            $document->file_type = 'application/pdf';
            $document->file_extension = 'pdf';
        }

        // Guided builder flow (step 1 → step 2): a PDF goes straight into the
        // field-placement builder; other file types are saved to the library.
        $isPdf = $document->file_extension === 'pdf';
        if ($isPdf) {
            $document->requires_signature = true;
        }

        $document->save();

        $this->syncAccess($document, $request);
        $this->syncSignatureTemplate($document);

        // PDF → open the placement builder directly on the "place fields" step.
        if ($isPdf && $document->template_id) {
            return redirect()->route('company-documents.place-fields', ['document' => $document->id, 'place' => 1])
                ->with('success', 'Document uploaded — now drag your variables onto the document.');
        }

        return redirect()->route('company-documents.admin')
            ->with('success', 'Document uploaded.' . ($isPdf ? '' : ' Upload a PDF to place variables and send for signature.'));
    }

    public function edit(CompanyDocument $document)
    {
        return view('company-documents.create', [
            'document' => $document->load('accessRecords', 'template.signers'),
            'categories' => DocumentCategory::orderBy('sort_order')->get(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'users' => User::where('account_status', '!=', 'deactivated')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function update(Request $request, CompanyDocument $document)
    {
        $data = $this->validateDocument($request, false);
        $category = DocumentCategory::findOrFail($data['category_id']);

        $document->fill($this->payload($request, $data));

        if ($request->hasFile('file')) {
            if ($document->file_path && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }
            $this->storeFile($document, $request, $category);

            // A replacement Word file goes through the browser editor too.
            if (in_array($document->file_extension, ['doc', 'docx'], true)) {
                $html = app(\App\Services\DocumentConversionService::class)->toEditableHtml($document->file_path);
                if ($html !== null) {
                    $document->save();
                    $this->syncAccess($document, $request);
                    Storage::put($this->editHtmlPath($document), $html);

                    return redirect()->route('company-documents.edit-content', $document)
                        ->with('success', 'New file uploaded — edit the text below, then click “Convert to PDF”.');
                }
            }
        }

        if ($document->requires_signature && $document->file_extension !== 'pdf') {
            return back()->withInput()->withErrors(['file' => 'Documents sent for signature must be a PDF.']);
        }

        $document->save();

        $this->syncAccess($document, $request);
        $this->syncSignatureTemplate($document);

        // Just turned on signatures but no signers set up yet → go finish the steps.
        if ($document->isSignable() && $document->template && $document->template->signers()->count() === 0) {
            return redirect()->route('company-documents.place-fields', $document)
                ->with('success', 'Document saved — now add signers and place the fields.');
        }

        return redirect()->route('company-documents.admin')->with('success', 'Document updated.');
    }

    /**
     * Step 1b — edit the text of an uploaded Word document in the browser
     * before it is converted to PDF for field placement.
     */
    public function editContent(CompanyDocument $document)
    {
        // Edit the Word source: the file itself while it's still Word, or the
        // ".source.docx" kept beside the PDF once converted — so a document that
        // has already been converted (and sent) can be reopened for editing here
        // instead of being stuck as a PDF.
        $docx = $document->editableWordPath();
        abort_unless($docx, 404);

        $service = app(\App\Services\DocumentConversionService::class);
        $htmlPath = $this->editHtmlPath($document);
        // A converted (PDF) document reopens fresh from its kept Word source (it
        // carries the tokens/edits); an in-progress Word edit keeps its cached
        // HTML so unsaved edits survive a page reload.
        $html = ($document->file_extension !== 'pdf' && Storage::exists($htmlPath))
            ? Storage::get($htmlPath)
            : $service->toEditableHtml($docx);

        if ($html === null) {
            return redirect()->route('company-documents.edit', $document)
                ->withErrors(['file' => 'Couldn’t open this Word file for editing — please re-upload it, or upload a PDF.']);
        }

        // Profile-field token names admins can insert — built from the standard
        // fields PLUS every profile field, so newly-added fields appear here too.
        $catalog = app(\App\Services\DocumentTokenService::class)->availableTokens();
        $tokenGroups = [];
        foreach ($catalog as $group => $items) {
            $tokenGroups[$group] = array_map(fn ($i) => $i['token'], $items);
        }

        // Fonts this document needs that the server can't render — the cause of
        // shifted layout in the converted PDF.
        $missingFonts = $service->missingFonts($docx);

        return view('company-documents.edit-content', compact('document', 'html', 'tokenGroups', 'missingFonts'));
    }

    /** Convert the (edited) HTML to PDF and continue to field placement. */
    public function convertToPdf(Request $request, CompanyDocument $document)
    {
        $docx = $document->editableWordPath();
        abort_unless($docx, 404);

        $request->validate(['html' => 'required|string|max:8000000']);

        // The PDF sits beside the Word source, cleanly named (strip .source.docx
        // / .docx), so re-converting overwrites the same PDF rather than piling up
        // ".source.pdf"-style names.
        $base = preg_replace('/(\.source)?\.docx?$/i', '', $docx);
        $target = ($base !== $docx ? $base : preg_replace('/\.[^.]+$/', '', $docx)) . '.pdf';

        $pdfPath = app(\App\Services\DocumentConversionService::class)
            ->htmlToPdf($request->input('html'), $target);
        if (!$pdfPath) {
            return back()->withErrors(['html' => 'Conversion to PDF failed — please try again, or upload a PDF instead.']);
        }

        return $this->swapToPdf($document, $pdfPath);
    }

    /**
     * Convert the ORIGINAL Word file straight to PDF, skipping the browser
     * editor — keeps letterheads, watermarks and floating artwork exactly as
     * designed in Word (the HTML editor can't hold floating objects in place).
     * Any tokens the admin added in the editor are injected into the Word file
     * first, so branding AND tokens survive together.
     */
    public function convertOriginal(Request $request, CompanyDocument $document)
    {
        // Word source: the file itself, or the kept ".source.docx" for a document
        // already converted to PDF (so it can be re-tokenised / reworded).
        $docx = $document->editableWordPath();
        abort_unless($docx, 404);

        $service = app(\App\Services\DocumentConversionService::class);

        // Body-text edits ([{find, replace}, …]) and, as a fallback, anchored
        // tokens. Edits carry everything the admin changed in the editor — reworded
        // text, removed spaces AND inserted tokens — so they're applied to a copy
        // of the .docx first; the Word branding (letterhead/watermark/header/footer)
        // lives in separate parts and is untouched.
        $edits = json_decode((string) $request->input('edits', '[]'), true) ?: [];
        $tokens = json_decode((string) $request->input('tokens', '[]'), true) ?: [];
        $hadChanges = (is_array($edits) && $edits) || (is_array($tokens) && $tokens);

        $source = $docx;
        $injected = null;
        if (is_array($edits) && $edits) {
            $injected = $service->applyEditsToDocx($docx, $edits);
        }
        if (!$injected && is_array($tokens) && $tokens) {
            $injected = $service->injectTokensIntoDocx($docx, $tokens);
        }
        if ($injected) {
            $source = $injected;
        }

        $pdfPath = $service->toPdf($source);

        if (!$pdfPath) {
            if ($injected && Storage::exists($injected)) {
                Storage::delete($injected);
            }
            return back()->withErrors(['html' => 'Conversion to PDF failed — please try again, or upload a PDF instead.']);
        }

        // Keep the token-bearing Word source alongside the PDF so a reader can
        // later regenerate a per-employee copy with tokens filled as real,
        // reflowed text (exact layout). Saved before swapToPdf removes the
        // original .docx.
        $srcTarget = preg_replace('/\.pdf$/i', '.source.docx', $pdfPath);
        if (Storage::exists($source)) {
            Storage::copy($source, $srcTarget);
        }
        if ($injected && Storage::exists($injected)) {
            Storage::delete($injected); // temp working copy — keep only PDF + source
        }

        $redirect = $this->swapToPdf($document, $pdfPath);

        // Changes were made but none could be matched into the Word layout —
        // don't fail silently; tell the admin how to get them in reliably.
        if ($hadChanges && !$injected) {
            $redirect->with('warning', 'Your document was converted with the Word layout, but the text edits couldn’t be matched automatically (this happens if whole paragraphs were added or removed). For those, edit the wording in Word itself and re-upload — the letterhead and watermark are always preserved.');
        }

        return $redirect;
    }

    /** Swap the stored Word file for the converted PDF and continue to placement. */
    private function swapToPdf(CompanyDocument $document, string $pdfPath)
    {
        // Keep the editable Word source beside the PDF so the document can be
        // reopened and edited later. convertOriginal may already have kept a
        // richer token-injected source — don't clobber it; otherwise (e.g. the
        // HTML-editor path, or a first convert) preserve the current .docx.
        $srcTarget = preg_replace('/\.pdf$/i', '.source.docx', $pdfPath);
        if (!Storage::exists($srcTarget)
            && in_array($document->file_extension, ['doc', 'docx'], true)
            && $document->file_path && Storage::exists($document->file_path)) {
            Storage::copy($document->file_path, $srcTarget);
        }

        // Drop the working Word file and the edit HTML — the PDF (and the kept
        // .source.docx) remain.
        foreach ([$document->file_path, $this->editHtmlPath($document)] as $old) {
            if ($old && $old !== $pdfPath && $old !== $srcTarget && Storage::exists($old)) {
                Storage::delete($old);
            }
        }
        $document->file_path = $pdfPath;
        $document->file_name = preg_replace('/\.[^.]+$/', '.pdf', $document->file_name);
        $document->file_type = 'application/pdf';
        $document->file_extension = 'pdf';
        $document->file_size = Storage::size($pdfPath);
        $document->requires_signature = true;
        $document->save();

        $this->syncSignatureTemplate($document);

        return redirect()->route('company-documents.place-fields', ['document' => $document->id, 'place' => 1])
            ->with('success', 'Converted to PDF — now drag your variables onto the document.');
    }

    /** Where the editable-HTML working copy of a Word upload lives. */
    private function editHtmlPath(CompanyDocument $document): string
    {
        return preg_replace('/\.[^.]+$/', '', $document->file_path) . '.edit.html';
    }

    public function newVersion(Request $request, CompanyDocument $document)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:' . self::MIMES, 'max:51200'],
            'version' => 'required|string|max:20',
            'version_notes' => 'nullable|string|max:1000',
        ]);

        $category = $document->category;
        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }
        $this->storeFile($document, $request, $category);
        $document->version = $request->version;
        $document->version_notes = $request->version_notes;
        $document->save();

        DocumentView::create(['document_id' => $document->id, 'user_id' => auth()->id(), 'action' => 'version_updated', 'created_at' => now()]);

        return back()->with('success', 'New version uploaded (v' . $document->version . ').');
    }

    public function destroy(CompanyDocument $document)
    {
        $document->delete();

        return redirect()->route('company-documents.admin')->with('success', 'Document deleted.');
    }

    // ----- Shared (access-controlled) --------------------------------------

    public function download(CompanyDocument $document)
    {
        abort_unless($document->isAccessibleBy(auth()->user()), 403, 'You do not have access to this document.');
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        $document->logView(auth()->user(), 'download');

        return Storage::download($document->file_path, $document->file_name);
    }

    public function view(CompanyDocument $document)
    {
        abort_unless($document->isAccessibleBy(auth()->user()), 403, 'You do not have access to this document.');
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        $document->logView(auth()->user(), 'view');

        return response(Storage::get($document->file_path), 200, [
            'Content-Type' => $document->file_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
        ]);
    }

    /**
     * Read a document with its [tokens] filled in for the CURRENT employee —
     * so a document set for acknowledgment (not signature) still shows the
     * reader's own name / CNIC / dates. Includes the acknowledgment action.
     */
    public function readDocument(CompanyDocument $document)
    {
        $user = auth()->user();
        abort_unless($document->isAccessibleBy($user), 403, 'You do not have access to this document.');
        abort_unless($document->file_extension === 'pdf' && $document->file_path && Storage::exists($document->file_path), 404);

        $document->logView($user, 'view');

        // Exact fill: if the Word source is kept AND LibreOffice is available,
        // serve a per-employee PDF with tokens as real, reflowed text. Otherwise
        // fall back to painting the values over the PDF (overlay).
        $exact = Storage::exists($this->sourceDocxPath($document))
            && app(\App\Services\DocumentConversionService::class)->available();

        $ack = $document->requires_acknowledgment ? $document->acknowledgmentFor($user) : null;

        // Values the employee already typed when acknowledging — overlaid so a
        // re-opened document shows the filled contract. The exact (regenerated)
        // PDF already bakes profile tokens, so it only needs these on top.
        $saved = ($ack && is_array($ack->field_values)) ? $ack->field_values : [];

        $fileUrl = $exact ? route('document-library.filled', $document) : route('document-library.view', $document);
        $tokens = $exact
            ? $saved
            : array_merge(app(\App\Services\DocumentTokenService::class)->profileTokens($user), $saved);

        // Hide signature placeholders on the read view so a raw [Employee
        // Signature] never shows as text — the signing flow is what stamps a
        // signature; when only reading, the token is blanked out.
        foreach ([
            '[Employee Signature]', "[Employee's Signature]", '[Candidate Signature]', '[candidate_signature]',
            '[Company Signature]', "[Sender's Signature]", "[Sender's signature]", '[Sender Signature]',
            '[company_signature]', '[Authorised Signature]', '[Authorized Signature]',
        ] as $sig) {
            if (!array_key_exists($sig, $tokens)) {
                $tokens[$sig] = '';
            }
        }

        // Only offer the fill-in form when acknowledgment is required and the
        // person hasn't acknowledged yet.
        $canFill = $document->requires_acknowledgment && !$ack;

        return view('company-documents.read', compact('document', 'tokens', 'ack', 'fileUrl', 'canFill'));
    }

    /** Where the kept Word source for a converted document lives (beside the PDF). */
    private function sourceDocxPath(CompanyDocument $document): string
    {
        return preg_replace('/\.pdf$/i', '.source.docx', (string) $document->file_path);
    }

    private function streamPdf(string $path, string $name)
    {
        return response(Storage::get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $name . '"',
        ]);
    }

    /**
     * Serve a per-employee PDF with the document's tokens filled as real text
     * (exact layout). Generated from the kept Word source and cached per user;
     * falls back to the raw PDF if there's no source or conversion fails.
     */
    public function filled(CompanyDocument $document)
    {
        $user = auth()->user();
        abort_unless($document->isAccessibleBy($user), 403, 'You do not have access to this document.');
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        $src = $this->sourceDocxPath($document);
        if (!Storage::exists($src)) {
            return $this->streamPdf($document->file_path, $document->file_name);
        }

        $cache = preg_replace('/\.pdf$/i', '.filled-' . $user->id . '.pdf', $document->file_path);
        $stale = !Storage::exists($cache) || Storage::lastModified($cache) < Storage::lastModified($src);

        if ($stale) {
            $conversion = app(\App\Services\DocumentConversionService::class);
            $tokens = app(\App\Services\DocumentTokenService::class)->profileTokens($user);
            $filledDocx = $conversion->fillTokensToDocx($src, $tokens);
            $pdf = $filledDocx ? $conversion->toPdf($filledDocx) : null;
            if ($filledDocx && Storage::exists($filledDocx)) {
                Storage::delete($filledDocx);
            }
            if ($pdf && Storage::exists($pdf)) {
                Storage::put($cache, Storage::get($pdf));
                Storage::delete($pdf);
            } else {
                return $this->streamPdf($document->file_path, $document->file_name); // fill failed → raw
            }
        }

        return $this->streamPdf($cache, $document->file_name);
    }

    /** Employee ticks the acknowledgment checkbox — record it once. */
    public function acknowledge(Request $request, CompanyDocument $document)
    {
        $user = $request->user();
        abort_unless($document->isAccessibleBy($user), 403, 'You do not have access to this document.');
        abort_unless($document->requires_acknowledgment, 400, 'This document does not require acknowledgment.');

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

        \App\Models\DocumentAcknowledgment::firstOrCreate(
            ['document_id' => $document->id, 'user_id' => $user->id],
            ['acknowledged_at' => now(), 'ip_address' => $request->ip(), 'field_values' => $fieldValues ?: null]
        );
        $document->logView($user, 'acknowledged');

        return back()->with('success', 'Thank you — your acknowledgment of “' . $document->title . '” has been recorded.');
    }

    // ----- Signing builder (steps 2–4, keyed by the company document) -------

    /** Step 2 — place fields & choose signers (renders the builder). */
    public function placeFields(CompanyDocument $document)
    {
        return app(DocumentTemplateController::class)->edit($this->ensureSigningTemplate($document));
    }

    /** Save placed fields & signers, then continue to preview. */
    public function saveFields(Request $request, CompanyDocument $document)
    {
        return app(DocumentTemplateController::class)->update($request, $this->ensureSigningTemplate($document));
    }

    /** Step 3 — preview the document with fields/signature spots. */
    public function previewSign(CompanyDocument $document)
    {
        return app(DocumentTemplateController::class)->previewView($this->ensureSigningTemplate($document));
    }

    /** Serve the document's file for the in-browser PDF renderer. */
    public function signingFile(CompanyDocument $document)
    {
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);
        $headers = $document->file_extension === 'pdf' ? ['Content-Type' => 'application/pdf'] : [];

        return Storage::response($document->file_path, $document->file_name, $headers);
    }

    /** Step 4 — pick the employee & signer chain. */
    public function sendForm(CompanyDocument $document)
    {
        return app(DocumentRequestController::class)->sendForm($this->ensureSigningTemplate($document));
    }

    /** Send for signature — creates the request + resolves signers. */
    public function send(Request $request, CompanyDocument $document)
    {
        return app(DocumentRequestController::class)->send($request, $this->ensureSigningTemplate($document));
    }

    /** The signing template backing a document — create it on demand if missing. */
    private function ensureSigningTemplate(CompanyDocument $document): DocumentTemplate
    {
        if (!$document->template_id) {
            $document->requires_signature = true;
            $document->save();
            $this->syncSignatureTemplate($document);
            $document->refresh();
        }

        return $document->template;
    }

    /** Admin: who has and hasn't acknowledged a document. */
    /** Per-recipient signing status: who signed, who is still pending. */
    public function signing(CompanyDocument $document)
    {
        abort_unless($document->requires_signature && $document->template_id, 404);

        $document->load([
            'template.requests' => fn ($q) => $q->latest('id'),
            'template.requests.subject.department',
            'template.requests.signers.user',
        ]);

        $requests = optional($document->template)->requests ?? collect();

        $rows = $requests->map(function ($r) {
            $total = $r->signers->count();
            $signed = $r->signers->where('status', 'signed')->count();
            $tones = [
                'completed' => ['Signed', 'emerald'],
                'declined' => ['Declined', 'rose'],
                'cancelled' => ['Cancelled', 'slate'],
            ];
            [$label, $tone] = $tones[$r->status] ?? ["Awaiting · {$signed}/{$total} signed", 'amber'];

            return [
                'request' => $r,
                'subject' => $r->subject,
                'status' => $r->status,
                'label' => $label,
                'tone' => $tone,
                'signed' => $signed,
                'total' => $total,
                'sent_at' => $r->created_at,
            ];
        });

        $stats = [
            'total' => $rows->count(),
            'signed' => $rows->where('status', 'completed')->count(),
            'declined' => $rows->where('status', 'declined')->count(),
            'pending' => $rows->whereNotIn('status', ['completed', 'declined', 'cancelled'])->count(),
        ];

        return view('company-documents.signing', compact('document', 'rows', 'stats'));
    }

    public function acknowledgments(CompanyDocument $document)
    {
        $eligible = $document->eligibleUsers();
        $acked = $document->acknowledgments()->with('employee.department')->get()->keyBy('user_id');

        $rows = $eligible->map(fn ($u) => [
            'user' => $u,
            'ack' => $acked->get($u->id),
        ])->sortBy(fn ($r) => [$r['ack'] ? 0 : 1, $r['user']->first_name])->values();

        return view('company-documents.acknowledgments', [
            'document' => $document,
            'rows' => $rows,
            'ackedCount' => $acked->count(),
            'total' => $eligible->count(),
        ]);
    }

    // ----- Employee --------------------------------------------------------

    public function employeeIndex()
    {
        $user = auth()->user();

        $documents = CompanyDocument::active()
            ->accessibleBy($user)
            ->with('category')
            ->latest()
            ->get();

        $categories = DocumentCategory::orderBy('sort_order')->get()
            ->filter(fn ($c) => $documents->where('category_id', $c->id)->isNotEmpty())
            ->values();

        // This user's acknowledgments, keyed by document, for the checkbox state.
        $acks = \App\Models\DocumentAcknowledgment::where('user_id', $user->id)
            ->whereIn('document_id', $documents->pluck('id'))
            ->get()->keyBy('document_id');

        // Documents sent to this person that are awaiting their signature.
        $toSign = \App\Models\DocumentRequest::with(['template', 'subject'])
            ->where('status', 'in_progress')
            ->whereHas('signers', fn ($s) => $s->where('user_id', $user->id)->where('status', 'pending'))
            ->get()
            ->filter(fn ($r) => $r->isAwaiting($user))
            ->values();

        return view('employee.documents.index', compact('documents', 'categories', 'toSign', 'acks'));
    }

    // ----- Helpers ---------------------------------------------------------

    private function validateDocument(Request $request, bool $fileRequired): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:document_categories,id',
            'description' => 'nullable|string|max:2000',
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:' . self::MIMES, 'max:51200'],
            'version' => 'nullable|string|max:20',
            'version_notes' => 'nullable|string|max:1000',
            'requires_signature' => 'nullable|boolean',
            'access_level' => ['required', Rule::in(['company_wide', 'department', 'specific_users'])],
            'departments' => 'nullable|array',
            'departments.*' => 'integer|exists:departments,id',
            'users' => 'nullable|array',
            'users.*' => 'integer|exists:users,id',
            'expires_at' => 'nullable|date',
        ]);
    }

    private function payload(Request $request, array $data): array
    {
        return [
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'version' => ($data['version'] ?? null) ?: '1.0',
            'version_notes' => $data['version_notes'] ?? null,
            'access_level' => $data['access_level'],
            'is_active' => $request->boolean('is_active', true),
            'requires_acknowledgment' => $request->boolean('requires_acknowledgment'),
            'requires_signature' => $request->boolean('requires_signature'),
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    /**
     * Keep a signing template in sync with a signable company document. The
     * template shares the document's stored file and drives the existing
     * signers / place-fields / preview / send-for-signature flow. Turning the
     * toggle off simply stops surfacing the actions — the template is kept so
     * re-enabling reuses its signer/field setup.
     */
    private function syncSignatureTemplate(CompanyDocument $document): void
    {
        if (!$document->requires_signature) {
            return;
        }

        $attrs = [
            'name' => $document->title,
            'description' => $document->description,
            'file_path' => $document->file_path,
            'file_name' => $document->file_name,
            'file_mime' => $document->file_type,
            'file_size' => $document->file_size,
        ];

        if ($document->template) {
            $document->template->update($attrs);

            return;
        }

        $template = DocumentTemplate::create(array_merge($attrs, [
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'tags' => [],
            'version' => 1,
            'status' => 'active',
        ]));

        $document->forceFill(['template_id' => $template->id])->saveQuietly();
    }

    private function storeFile(CompanyDocument $document, Request $request, DocumentCategory $category): void
    {
        $file = $request->file('file');
        $path = $file->store(\App\Tenancy\TenantStorage::path('company-documents/' . $category->slug));
        $document->file_path = $path;
        $document->file_name = $file->getClientOriginalName();
        $document->file_size = $file->getSize();
        $document->file_type = $file->getMimeType();
        $document->file_extension = strtolower($file->getClientOriginalExtension());
    }

    private function syncAccess(CompanyDocument $document, Request $request): void
    {
        $document->accessRecords()->delete();

        if ($document->access_level === 'department') {
            foreach ((array) $request->input('departments', []) as $id) {
                $document->accessRecords()->create(['access_type' => 'department', 'access_id' => $id]);
            }
        } elseif ($document->access_level === 'specific_users') {
            foreach ((array) $request->input('users', []) as $id) {
                $document->accessRecords()->create(['access_type' => 'user', 'access_id' => $id]);
            }
        }
    }
}
