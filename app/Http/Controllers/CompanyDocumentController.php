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
        $query = CompanyDocument::with(['category', 'uploader'])->latest();

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
            return redirect()->route('document-templates.edit', ['documentTemplate' => $document->template_id, 'place' => 1])
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
            return redirect()->route('document-templates.edit', $document->template_id)
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
        abort_unless(in_array($document->file_extension, ['doc', 'docx'], true), 404);

        $htmlPath = $this->editHtmlPath($document);
        $html = Storage::exists($htmlPath)
            ? Storage::get($htmlPath)
            : app(\App\Services\DocumentConversionService::class)->toEditableHtml($document->file_path);

        if ($html === null) {
            return redirect()->route('company-documents.edit', $document)
                ->withErrors(['file' => 'Couldn’t open this Word file for editing — please re-upload it, or upload a PDF.']);
        }

        // Profile-field token names admins can type/insert into the text.
        $tokenGroups = [
            'Employee' => ['[Employee Name]', '[CNIC]', '[Designation]', '[Department]', '[Email]', '[Joining Date]'],
            'Agreement' => ['[Date]', '[Salary]', '[Probation Salary]'],
            'Signatures' => ['[Employee Signature]', "[Sender's Signature]"],
        ];

        return view('company-documents.edit-content', compact('document', 'html', 'tokenGroups'));
    }

    /** Convert the (edited) HTML to PDF and continue to field placement. */
    public function convertToPdf(Request $request, CompanyDocument $document)
    {
        abort_unless(in_array($document->file_extension, ['doc', 'docx'], true), 404);

        $request->validate(['html' => 'required|string|max:8000000']);

        $target = preg_replace('/\.[^.]+$/', '.pdf', $document->file_path);
        if ($target === $document->file_path) {
            $target .= '.pdf';
        }

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
     */
    public function convertOriginal(CompanyDocument $document)
    {
        abort_unless(in_array($document->file_extension, ['doc', 'docx'], true), 404);

        $pdfPath = app(\App\Services\DocumentConversionService::class)->toPdf($document->file_path);
        if (!$pdfPath) {
            return back()->withErrors(['html' => 'Conversion to PDF failed — please try again, or upload a PDF instead.']);
        }

        return $this->swapToPdf($document, $pdfPath);
    }

    /** Swap the stored Word file for the converted PDF and continue to placement. */
    private function swapToPdf(CompanyDocument $document, string $pdfPath)
    {
        // Drop the Word file and the edit HTML — only the PDF remains.
        foreach ([$document->file_path, $this->editHtmlPath($document)] as $old) {
            if ($old && $old !== $pdfPath && Storage::exists($old)) {
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

        return redirect()->route('document-templates.edit', ['documentTemplate' => $document->template_id, 'place' => 1])
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

        // Documents sent to this person that are awaiting their signature.
        $toSign = \App\Models\DocumentRequest::with(['template', 'subject'])
            ->where('status', 'in_progress')
            ->whereHas('signers', fn ($s) => $s->where('user_id', $user->id)->where('status', 'pending'))
            ->get()
            ->filter(fn ($r) => $r->isAwaiting($user))
            ->values();

        return view('employee.documents.index', compact('documents', 'categories', 'toSign'));
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
            'version' => $data['version'] ?: '1.0',
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
