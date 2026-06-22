<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use App\Models\Department;
use App\Models\DocumentAccess;
use App\Models\DocumentCategory;
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
        $document->save();

        $this->syncAccess($document, $request);

        return redirect()->route('company-documents.admin')->with('success', 'Document uploaded.');
    }

    public function edit(CompanyDocument $document)
    {
        return view('company-documents.create', [
            'document' => $document->load('accessRecords'),
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
        }
        $document->save();

        $this->syncAccess($document, $request);

        return redirect()->route('company-documents.admin')->with('success', 'Document updated.');
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
        $documents = CompanyDocument::active()
            ->accessibleBy(auth()->user())
            ->with('category')
            ->latest()
            ->get();

        $categories = DocumentCategory::orderBy('sort_order')->get()
            ->filter(fn ($c) => $documents->where('category_id', $c->id)->isNotEmpty())
            ->values();

        return view('employee.documents.index', compact('documents', 'categories'));
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
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    private function storeFile(CompanyDocument $document, Request $request, DocumentCategory $category): void
    {
        $file = $request->file('file');
        $path = $file->store('company-documents/' . $category->slug);
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
