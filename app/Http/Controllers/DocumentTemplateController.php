<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntity;
use App\Models\Department;
use App\Models\DocumentTemplate;
use App\Models\ProfileSection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentTemplateController extends Controller
{
    /** Accepted upload formats (Workable parity). */
    private const ACCEPTED_MIMES = 'pdf,doc,docx,ppt,pptx';
    private const MAX_KB = 10240; // 10 MB

    /** Template list + archived section. */
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        $base = DocumentTemplate::withCount('entities')
            ->with('creator:id,first_name,last_name')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->latest('updated_at');

        $templates = (clone $base)->where('status', 'active')->get();
        $archived = (clone $base)->where('status', 'archived')->get();

        return view('document-templates.index', compact('templates', 'archived', 'search'));
    }

    /** Create wizard. */
    public function create()
    {
        return view('document-templates.wizard', $this->wizardData(null));
    }

    /** Edit wizard. */
    public function edit(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->load(['entities', 'departments', 'signers.employee', 'fields']);

        return view('document-templates.wizard', $this->wizardData($documentTemplate));
    }

    /** Persist a new template. */
    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request, true);

        $file = $request->file('file');
        $path = $file->store('document-templates');

        $template = DocumentTemplate::create([
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'tags' => $this->cleanTags($request->input('tags', [])),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'version' => 1,
            'status' => 'active',
        ]);

        $this->syncRelations($template, $request);

        return redirect()->route('document-templates.index')
            ->with('success', "Document template “{$template->name}” created.");
    }

    /** Update an existing template (replacing the file = new version). */
    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $validated = $this->validateTemplate($request, false);

        $documentTemplate->name = $validated['name'];
        $documentTemplate->description = $validated['description'] ?? null;
        $documentTemplate->tags = $this->cleanTags($request->input('tags', []));

        if ($request->hasFile('file')) {
            // Replace file → bump version, drop the old file.
            if ($documentTemplate->file_path && Storage::exists($documentTemplate->file_path)) {
                Storage::delete($documentTemplate->file_path);
            }
            $file = $request->file('file');
            $documentTemplate->file_path = $file->store('document-templates');
            $documentTemplate->file_name = $file->getClientOriginalName();
            $documentTemplate->file_mime = $file->getClientMimeType();
            $documentTemplate->file_size = $file->getSize();
            $documentTemplate->version = $documentTemplate->version + 1;
        }

        $documentTemplate->save();

        $this->syncRelations($documentTemplate, $request);

        return redirect()->route('document-templates.index')
            ->with('success', "Document template “{$documentTemplate->name}” updated.");
    }

    /** Soft-archive a template. */
    public function archive(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->update(['status' => 'archived']);

        return back()->with('success', "“{$documentTemplate->name}” archived.");
    }

    /** Restore an archived template. */
    public function restore(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->update(['status' => 'active']);

        return back()->with('success', "“{$documentTemplate->name}” restored.");
    }

    /** Permanently delete a template + its file. */
    public function destroy(DocumentTemplate $documentTemplate)
    {
        if ($documentTemplate->file_path && Storage::exists($documentTemplate->file_path)) {
            Storage::delete($documentTemplate->file_path);
        }
        $name = $documentTemplate->name;
        $documentTemplate->delete();

        return back()->with('success', "“{$name}” deleted permanently.");
    }

    /** Stream the uploaded file inline (Preview). */
    public function preview(DocumentTemplate $documentTemplate)
    {
        if (!$documentTemplate->file_path || !Storage::exists($documentTemplate->file_path)) {
            abort(404, 'Template file not found.');
        }

        $headers = $documentTemplate->isPdf() ? ['Content-Type' => 'application/pdf'] : [];

        return Storage::response($documentTemplate->file_path, $documentTemplate->file_name, $headers);
    }

    /** Rich preview — render the document with placed fields + a test-sign experience. */
    public function previewView(DocumentTemplate $documentTemplate)
    {
        $documentTemplate->load(['fields', 'signers.employee']);

        // Non-PDF templates can't render in-browser — show a download panel instead.
        if (!$documentTemplate->isPdf()) {
            return view('document-templates.preview-view', [
                'documentTemplate' => $documentTemplate,
                'fields' => collect(),
                'signers' => collect(),
                'isPdf' => false,
            ]);
        }

        $fields = $documentTemplate->fields
            ->filter(fn ($f) => $f->page)
            ->map(fn ($f) => [
                'label' => $f->label,
                'type' => $f->field_type,
                'assignee' => $f->assignee,
                'page' => (int) $f->page,
                'x' => (float) $f->pos_x,
                'y' => (float) $f->pos_y,
                'w' => (float) $f->width,
                'h' => (float) $f->height,
            ])->values();

        $signers = $documentTemplate->signers->map(fn ($s) => [
            'name' => $s->display_name,
            'role' => $s->signer_type === 'employee' ? 'specific' : ($s->role ?? 'specific'),
        ])->values();

        return view('document-templates.preview-view', compact('documentTemplate', 'fields', 'signers') + ['isPdf' => true]);
    }

    /** Generate page — pick an employee, then produce the auto-filled PDF in the browser. */
    public function generate(DocumentTemplate $documentTemplate)
    {
        abort_unless($documentTemplate->isPdf(), 422, 'Auto-fill generation is available for PDF templates only.');

        $documentTemplate->load('fields');

        // Eligible employees, honouring the template's entity/department scope.
        $entityIds = $documentTemplate->entities()->pluck('company_entities.id')->all();
        $deptIds = $documentTemplate->departments()->pluck('departments.id')->all();

        $employees = User::where('account_status', '!=', 'deactivated')
            ->when($entityIds, fn ($q) => $q->whereIn('company_entity_id', $entityIds))
            ->when($deptIds, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'job_title']);

        return view('document-templates.generate', compact('documentTemplate', 'employees'));
    }

    /** JSON: resolved field values + placement for a given employee. */
    public function fillData(Request $request, DocumentTemplate $documentTemplate)
    {
        $employee = User::findOrFail($request->integer('employee'));
        $documentTemplate->load('fields');

        $fields = $documentTemplate->fields
            ->filter(fn ($f) => $f->page) // only placed fields
            ->map(function ($f) use ($employee) {
                $value = '';
                if ($f->field_type === 'text' && $f->field_key) {
                    $value = (string) ($employee->getFieldValue($f->field_key) ?? '');
                } elseif ($f->field_type === 'signature') {
                    $value = '';
                } elseif ($f->field_type === 'initials') {
                    $value = '';
                }

                return [
                    'label' => $f->label,
                    'type' => $f->field_type,
                    'assignee' => $f->assignee,
                    'value' => $value,
                    'page' => (int) $f->page,
                    'x' => (float) $f->pos_x,
                    'y' => (float) $f->pos_y,
                    'w' => (float) $f->width,
                    'h' => (float) $f->height,
                ];
            })->values();

        return response()->json([
            'fileUrl' => route('document-templates.preview', $documentTemplate),
            'fileName' => pathinfo($documentTemplate->file_name, PATHINFO_FILENAME),
            'employee' => trim($employee->first_name . ' ' . $employee->last_name),
            'fields' => $fields,
        ]);
    }

    // ---------------------------------------------------------------------

    /** Shared validation rules. */
    private function validateTemplate(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => 'required|string|max:70',
            'description' => 'nullable|string|max:140',
            'file' => ($creating ? 'required' : 'nullable') . '|file|max:' . self::MAX_KB . '|mimes:' . self::ACCEPTED_MIMES,
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:50',
            'entities' => 'nullable|array',
            'entities.*' => 'integer|exists:company_entities,id',
            'departments' => 'nullable|array',
            'departments.*' => 'integer|exists:departments,id',
            'signers' => 'nullable|array',
            'signers.*.signer_type' => ['required', Rule::in(['role', 'employee'])],
            'signers.*.role' => 'nullable|string|in:employee,line_manager,hr_admin,me_now,sender',
            'signers.*.employee_id' => 'nullable|integer|exists:users,id',
            'fields' => 'nullable|array',
            'fields.*.field_key' => 'nullable|string|max:255',
            'fields.*.label' => 'nullable|string|max:255',
            'fields.*.section' => 'nullable|string|max:255',
            'fields.*.field_type' => 'nullable|string|in:text,signature,initials',
            'fields.*.assignee' => 'nullable|string|in:employee,sender,hr_admin,me_now',
            'fields.*.profile_field_id' => 'nullable|integer|exists:profile_fields,id',
            'fields.*.page' => 'nullable|integer|min:1',
            'fields.*.pos_x' => 'nullable|numeric',
            'fields.*.pos_y' => 'nullable|numeric',
            'fields.*.width' => 'nullable|numeric',
            'fields.*.height' => 'nullable|numeric',
        ]);
    }

    /** Sync scoping, signers and fields from the request. */
    private function syncRelations(DocumentTemplate $template, Request $request): void
    {
        DB::transaction(function () use ($template, $request) {
            $template->entities()->sync($request->input('entities', []));
            $template->departments()->sync($request->input('departments', []));

            // Signers — rebuild in order.
            $template->signers()->delete();
            foreach (array_values($request->input('signers', [])) as $i => $signer) {
                $type = $signer['signer_type'] ?? 'role';
                $template->signers()->create([
                    'position' => $i,
                    'signer_type' => $type,
                    'role' => $type === 'role' ? ($signer['role'] ?? 'employee') : null,
                    'employee_id' => $type === 'employee' ? (($signer['employee_id'] ?? null) ?: null) : null,
                ]);
            }

            // Fields — rebuild selection (with optional placement coordinates).
            $template->fields()->delete();
            foreach (array_values($request->input('fields', [])) as $field) {
                if (empty($field['field_key']) && empty($field['profile_field_id'])) {
                    continue;
                }
                $template->fields()->create([
                    'profile_field_id' => ($field['profile_field_id'] ?? null) ?: null,
                    'field_key' => $field['field_key'] ?? null,
                    'label' => $field['label'] ?? null,
                    'section' => $field['section'] ?? null,
                    'field_type' => $field['field_type'] ?? 'text',
                    'assignee' => $field['assignee'] ?? 'employee',
                    'page' => ($field['page'] ?? null) ?: null,
                    'pos_x' => isset($field['pos_x']) && $field['pos_x'] !== '' ? $field['pos_x'] : null,
                    'pos_y' => isset($field['pos_y']) && $field['pos_y'] !== '' ? $field['pos_y'] : null,
                    'width' => isset($field['width']) && $field['width'] !== '' ? $field['width'] : null,
                    'height' => isset($field['height']) && $field['height'] !== '' ? $field['height'] : null,
                ]);
            }
        });
    }

    /** Normalize tags to a clean string array. */
    private function cleanTags($tags): array
    {
        return collect(is_array($tags) ? $tags : [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Shared data for the create/edit wizard. */
    private function wizardData(?DocumentTemplate $template): array
    {
        // Profile fields grouped by section, for Step 3.
        $sections = ProfileSection::with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $entities = CompanyEntity::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $employees = User::where('account_status', '!=', 'deactivated')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'job_title']);

        // Pre-fill state for edit mode (JSON-friendly).
        $existing = null;
        if ($template) {
            $existing = [
                'name' => $template->name,
                'description' => $template->description,
                'tags' => $template->tags ?? [],
                'fileName' => $template->file_name,
                'fileUrl' => $template->isPdf() ? route('document-templates.preview', $template) : null,
                'isPdf' => $template->isPdf(),
                'entities' => $template->entities->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'departments' => $template->departments->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'signers' => $template->signers->map(fn ($s) => [
                    'signer_type' => $s->signer_type,
                    'role' => $s->role,
                    'employee_id' => $s->employee_id ? (string) $s->employee_id : '',
                ])->values()->all(),
                'selections' => $template->fields->mapWithKeys(fn ($f) => [
                    ($f->field_key ?: ('field_' . $f->id)) => [
                        'key' => $f->field_key ?: ('field_' . $f->id),
                        'label' => $f->label,
                        'section' => $f->section,
                        'type' => $f->field_type,
                        'assignee' => $f->assignee,
                        'id' => $f->profile_field_id,
                        'placement' => $f->page ? [
                            'page' => (int) $f->page,
                            'x' => (float) $f->pos_x,
                            'y' => (float) $f->pos_y,
                            'w' => (float) $f->width,
                            'h' => (float) $f->height,
                        ] : null,
                    ],
                ])->all(),
            ];
        }

        return [
            'template' => $template,
            'sections' => $sections,
            'entities' => $entities,
            'departments' => $departments,
            'employees' => $employees,
            'existing' => $existing,
        ];
    }
}
