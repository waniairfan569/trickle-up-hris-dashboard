<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\HrDocument;
use App\Models\HrDocumentTemplate;
use App\Models\User;
use App\Services\HrDocumentPrefillService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HrDocumentController extends Controller
{
    /** Field types the builder + renderer understand. */
    private const TYPES = ['text', 'textarea', 'number', 'date', 'checkbox', 'radio', 'select', 'table', 'signature', 'note'];

    public function __construct(private HrDocumentPrefillService $prefiller) {}

    /** Landing page: template library + recent (or archived) filled documents. */
    public function index(Request $request)
    {
        $templates = HrDocumentTemplate::query()
            ->orderBy('sort_order')->orderBy('name')->get();

        $showArchived = $request->boolean('archived');

        $documents = HrDocument::with('employee')
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->latest()->limit(100)->get();

        $archivedCount = HrDocument::whereNotNull('archived_at')->count();

        return view('hr-documents.index', compact('templates', 'documents', 'showArchived', 'archivedCount'));
    }

    // ── Template builder ───────────────────────────────────────────

    public function createTemplate()
    {
        return view('hr-documents.builder', [
            'template' => new HrDocumentTemplate(['schema' => []]),
            'types'    => self::TYPES,
        ]);
    }

    public function editTemplate(HrDocumentTemplate $template)
    {
        return view('hr-documents.builder', [
            'template' => $template,
            'types'    => self::TYPES,
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $data = $this->validateTemplate($request);
        $data['created_by'] = auth()->id();

        $template = HrDocumentTemplate::create($data);

        return redirect()->route('hr-documents.index')
            ->with('success', "Template “{$template->name}” created.");
    }

    public function updateTemplate(Request $request, HrDocumentTemplate $template)
    {
        $template->update($this->validateTemplate($request));

        return redirect()->route('hr-documents.index')
            ->with('success', "Template “{$template->name}” updated.");
    }

    public function destroyTemplate(HrDocumentTemplate $template)
    {
        $template->delete();

        return redirect()->route('hr-documents.index')
            ->with('success', 'Template deleted.');
    }

    // ── Fill / edit a document ─────────────────────────────────────

    /** Show the fillable form for a template (optionally prefilled for an employee/period). */
    public function create(Request $request)
    {
        $template = HrDocumentTemplate::findOrFail($request->integer('template'));

        $employee = $request->filled('employee')
            ? User::with(['department', 'manager'])->find($request->integer('employee'))
            : null;

        [$start, $end, $monthValue] = $this->resolveMonth($request->input('month'));

        $prefill = [];
        if ($employee) {
            $prefill = $this->prefiller->prefill($template, $employee, $start, $end);
        }

        return view('hr-documents.form', [
            'template'   => $template,
            'employees'  => $this->employeeOptions(),
            'employee'   => $employee,
            'month'      => $monthValue,
            'prefill'    => $prefill,
            'document'   => null,
        ]);
    }

    public function store(Request $request)
    {
        $template = HrDocumentTemplate::findOrFail($request->integer('template_id'));
        $employee = User::findOrFail($request->integer('employee_id'));

        [$start, $end] = $this->resolveMonth($request->input('month'));
        $values = $this->decodeData($request->input('data'));

        $document = HrDocument::create([
            'hr_document_template_id' => $template->id,
            'user_id'       => $employee->id,
            'template_name' => $template->name,
            'title'         => $this->buildTitle($template, $start),
            'schema'        => $template->schema,   // snapshot
            'data'          => $values,
            'period_start'  => $start,
            'period_end'    => $end,
            'status'        => $request->input('action') === 'complete' ? 'completed' : 'draft',
            'created_by'    => auth()->id(),
        ]);

        return redirect()->route('hr-documents.show', $document)
            ->with('success', 'Document saved.');
    }

    public function edit(HrDocument $document)
    {
        return view('hr-documents.form', [
            'template'   => $document->template ?? new HrDocumentTemplate(['schema' => $document->schema, 'name' => $document->template_name]),
            'employees'  => $this->employeeOptions(),
            'employee'   => $document->employee,
            'month'      => optional($document->period_start)->format('Y-m'),
            'prefill'    => $document->data ?? [],
            'document'   => $document,
        ]);
    }

    public function update(Request $request, HrDocument $document)
    {
        $values = $this->decodeData($request->input('data'));

        $document->update([
            'data'   => $values,
            'status' => $request->input('action') === 'complete' ? 'completed' : $document->status,
        ]);

        return redirect()->route('hr-documents.show', $document)
            ->with('success', 'Document updated.');
    }

    public function show(HrDocument $document)
    {
        $document->load('employee', 'creator', 'signers.user');

        $employees = $this->employeeOptions();

        return view('hr-documents.show', compact('document', 'employees'));
    }

    public function pdf(HrDocument $document, Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $pdf = Pdf::loadView('hr-documents.pdf', ['document' => $document])
            ->setPaper('a4', 'portrait');

        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $name = Str::of($document->template_name . ' ' . optional($document->employee)->full_name)
            ->slug('_')->limit(60, '')->toString();

        // ?preview=1 opens the PDF inline in the browser instead of downloading.
        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.($name ?: 'hr-document').'.pdf"',
            'Content-Length'      => (string) strlen($content),
        ]);
    }

    /** Live PDF preview of an unsaved fill form (opens inline, nothing stored). */
    public function preview(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $template = HrDocumentTemplate::findOrFail($request->integer('template_id'));

        $document = new HrDocument([
            'hr_document_template_id' => $template->id,
            'user_id'       => $request->integer('employee_id') ?: null,
            'template_name' => $template->name,
            'schema'        => $template->schema,
            'data'          => $this->decodeData($request->input('data')),
        ]);
        $document->setRelation('template', $template);
        if ($document->user_id) {
            $document->setRelation('employee', User::find($document->user_id));
        }

        $pdf = Pdf::loadView('hr-documents.pdf', ['document' => $document])->setPaper('a4', 'portrait');
        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    /** Send a document to its employee (and optionally a manager) to sign in-app. */
    public function send(Request $request, HrDocument $document)
    {
        $sigFields = collect($document->signatureFields());
        if ($sigFields->isEmpty()) {
            return back()->with('error', 'This document has no signature fields, so there is nothing to sign.');
        }

        $employee = $document->employee;

        $isMgr     = fn ($f) => Str::contains(Str::lower($f['label'] ?? ''), 'manager');
        $mgrFields = $sigFields->filter($isMgr);
        $empFields = $sigFields->reject($isMgr);

        // Admin's choices: whether the manager must sign, and who that manager is.
        $includeManager = $request->boolean('include_manager') && $mgrFields->isNotEmpty();
        $managerId      = $request->integer('manager_id') ?: optional($employee)->manager_id;
        $manager        = $includeManager && $managerId ? User::find($managerId) : null;

        // Rebuild the signer set (supports re-sending).
        $document->signers()->delete();
        $sentTo = [];

        if ($employee && $empFields->isNotEmpty()) {
            $document->signers()->create(['user_id' => $employee->id, 'role' => 'employee', 'field_ids' => $empFields->pluck('id')->all()]);
            $employee->notify(new \App\Notifications\HrDocumentSignatureRequested($document));
            $sentTo[] = $employee->full_name;
        }

        if ($manager) {
            $document->signers()->create(['user_id' => $manager->id, 'role' => 'manager', 'field_ids' => $mgrFields->pluck('id')->all()]);
            $manager->notify(new \App\Notifications\HrDocumentSignatureRequested($document));
            $sentTo[] = $manager->full_name;
        }

        if (empty($sentTo)) {
            return back()->with('error', 'Could not send: no signer was selected. Pick at least the employee or a manager.');
        }

        $document->update(['status' => 'sent', 'sent_at' => now()]);

        $note = ($includeManager && ! $manager) ? ' (manager signature was requested but no manager was chosen — sign that field yourself via Edit or resend)' : '';

        return back()->with('success', 'Sent for signature to ' . collect($sentTo)->join(' and ') . '.' . $note);
    }

    /** Editable Word (.docx) export of a filled document. */
    public function docx(HrDocument $document, \App\Services\HrDocumentWordService $word)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $path = $word->build($document);

        $name = Str::of($document->template_name . ' ' . optional($document->employee)->full_name)
            ->slug('_')->limit(60, '')->toString();

        return response()->download($path, ($name ?: 'hr-document') . '.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function archive(HrDocument $document)
    {
        $document->update(['archived_at' => now()]);

        return back()->with('success', 'Document archived.');
    }

    public function unarchive(HrDocument $document)
    {
        $document->update(['archived_at' => null]);

        return back()->with('success', 'Document restored.');
    }

    public function destroy(HrDocument $document)
    {
        $document->delete();

        return redirect()->route('hr-documents.index')
            ->with('success', 'Document deleted.');
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function validateTemplate(Request $request): array
    {
        $request->validate([
            'name'        => 'required|string|max:150',
            'subtitle'    => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:50',
            'schema'      => 'required|string',
        ]);

        return [
            'name'        => $request->input('name'),
            'subtitle'    => $request->input('subtitle'),
            'description' => $request->input('description'),
            'icon'        => $request->input('icon') ?: 'file-text',
            'prefill'     => $request->input('prefill') ?: null,
            'schema'      => $this->normalizeSchema($this->decodeData($request->input('schema'))),
            'is_active'   => (bool) $request->boolean('is_active', true),
        ];
    }

    /** Clean an incoming schema: valid types, unique non-empty field ids. */
    private function normalizeSchema($schema): array
    {
        $out = [];
        $seen = [];

        foreach ((array) $schema as $section) {
            $fields = [];
            foreach ($section['fields'] ?? [] as $field) {
                $type = in_array($field['type'] ?? '', self::TYPES, true) ? $field['type'] : 'text';

                $id = Str::slug($field['id'] ?? $field['label'] ?? 'field', '_') ?: 'field';
                $base = $id;
                $n = 2;
                while (in_array($id, $seen, true)) {
                    $id = $base . '_' . $n++;
                }
                $seen[] = $id;

                $clean = [
                    'id'    => $id,
                    'label' => (string) ($field['label'] ?? ''),
                    'type'  => $type,
                    'width' => ($field['width'] ?? 'full') === 'half' ? 'half' : 'full',
                ];
                if (in_array($type, ['checkbox', 'radio', 'select'], true)) {
                    $clean['options'] = array_values(array_filter(array_map('trim', (array) ($field['options'] ?? [])), 'strlen'));
                }
                if ($type === 'table') {
                    $clean['columns'] = array_values(array_filter(array_map('trim', (array) ($field['columns'] ?? ['Column 1'])), 'strlen')) ?: ['Column 1'];
                }
                if ($type === 'note') {
                    $clean['text'] = (string) ($field['text'] ?? '');
                }
                if (! empty($field['prefill'])) {
                    $clean['prefill'] = (string) $field['prefill'];
                }
                if (! empty($field['placeholder'])) {
                    $clean['placeholder'] = (string) $field['placeholder'];
                }

                $fields[] = $clean;
            }

            $out[] = [
                'title'  => (string) ($section['title'] ?? 'Section'),
                'fields' => $fields,
            ];
        }

        return $out;
    }

    /** Decode a JSON string (from a hidden input) into an array; tolerant of arrays. */
    private function decodeData($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** [start, end, 'Y-m'] for a given YYYY-MM (defaults to the current month). */
    private function resolveMonth(?string $month): array
    {
        try {
            $base = $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable $e) {
            $base = Carbon::now()->startOfMonth();
        }

        return [$base->copy()->startOfMonth(), $base->copy()->endOfMonth(), $base->format('Y-m')];
    }

    private function buildTitle(HrDocumentTemplate $template, Carbon $start): string
    {
        return $template->name . ' — ' . $start->format('M Y');
    }

    /** Active, real employees for the picker. */
    private function employeeOptions()
    {
        $realIds = Employee::real()->pluck('user_id')->filter()->all();

        return User::active()
            ->whereIn('id', $realIds)
            ->with('department')
            ->orderBy('first_name')->orderBy('last_name')
            ->get()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->full_name,
                'department' => optional($u->department)->name ?? '—',
            ])->values();
    }
}
