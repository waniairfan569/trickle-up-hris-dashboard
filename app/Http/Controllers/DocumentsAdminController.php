<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use App\Models\CompanyForm;
use App\Models\CompanyPolicy;
use App\Models\DocumentCategory;
use App\Models\HrDocument;
use App\Models\HrDocumentTemplate;
use App\Models\LetterheadTemplate;
use App\Models\SignatureTemplate;
use Illuminate\Http\Request;

/**
 * Documents hub — one landing page for everything an admin sends to people and
 * gets something back from.
 *
 * These pages were spread across three unrelated hubs: forms, e-signature and
 * policies under Company, signature blocks and letterheads under Templates, and
 * HR documents under Time & Attendance. The document library was in none of
 * them and could only be reached by typing the URL. Employees have had a single
 * Documents page for a while; this gives admins the same.
 *
 * Tiles are grouped by what you start with, because that is the real difference
 * between them — an uploaded file, a template that fills itself, written text,
 * or questions you ask.
 */
class DocumentsAdminController extends Controller
{
    public function index()
    {
        $groups = [];

        // --- Documents that go out for signature ---
        $signing = [];

        if (plan_allows('hr_documents')) {
            $signing[] = [
                'route' => 'hr-documents.index',
                'icon'  => 'file-signature',
                'tone'  => 'brand',
                'title' => 'HR documents',
                'text'  => 'Templates that fill themselves from your own data, generate per employee and route for signature.',
                'stat'  => HrDocumentTemplate::where('is_active', true)->count(),
                'stat_label' => 'templates',
                'meta'  => HrDocument::count() . ' generated',
            ];
        }

        // One page, two jobs: the same uploaded files are both the staff library
        // and what gets sent for signature. The module catalogue splits them in
        // two ("documents" and "esign") but there is only one screen behind both.
        if (plan_allows('esign') || plan_allows('documents')) {
            $signing[] = [
                'route' => 'company-documents.admin',
                'icon'  => 'pen-tool',
                'tone'  => 'indigo',
                'title' => 'Company documents',
                'text'  => plan_allows('esign')
                    ? 'Files you upload — share them with staff, or place signature boxes on one and send it to be signed.'
                    : 'Files you upload and share with staff, organised by category, with views recorded.',
                'stat'  => CompanyDocument::count(),
                'stat_label' => 'documents',
                'meta'  => plan_allows('esign') ? 'share or sign' : 'share and track',
            ];
        }

        if ($signing) {
            $groups[] = ['label' => 'Sent for signature', 'tiles' => $signing];
        }

        // --- Published for people to read or acknowledge ---
        $published = [];

        if (plan_allows('policies')) {
            $published[] = [
                'route' => 'company-policies.index',
                'icon'  => 'scroll-text',
                'tone'  => 'amber',
                'title' => 'Company policies',
                'text'  => 'Policy text staff must read and acknowledge, with assignment and tracking.',
                'stat'  => CompanyPolicy::count(),
                'stat_label' => 'policies',
                'meta'  => 'read and acknowledge',
            ];
        }

        if ($published) {
            $groups[] = ['label' => 'Published to staff', 'tiles' => $published];
        }

        // --- Questions you ask people ---
        if (plan_allows('forms')) {
            $groups[] = ['label' => 'Asked of people', 'tiles' => [[
                'route' => 'company-forms.index',
                'icon'  => 'clipboard-list',
                'tone'  => 'emerald',
                'title' => 'Custom forms',
                'text'  => 'Questions you put to staff, with reviewers, submissions and export.',
                'stat'  => CompanyForm::count(),
                'stat_label' => 'forms',
                'meta'  => 'collect answers',
            ]]];
        }

        // --- The pieces documents are built from ---
        $parts = [
            [
                'route' => 'letterheads.index',
                'icon'  => 'layout-template',
                'tone'  => 'slate',
                'title' => 'Letterheads',
                'text'  => 'Branded header and footer applied to generated documents.',
                'stat'  => LetterheadTemplate::count(),
                'stat_label' => 'letterheads',
                'meta'  => 'branding',
            ],
            [
                'route' => 'signature-templates.index',
                'icon'  => 'signature',
                'tone'  => 'slate',
                'title' => 'Signature blocks',
                'text'  => 'Saved signatures to stamp onto letters and documents.',
                'stat'  => SignatureTemplate::count(),
                'stat_label' => 'saved',
                'meta'  => 'reusable',
            ],
        ];

        if (plan_allows('documents')) {
            $parts[] = [
                // .index returns JSON for the pickers; .manage is the page.
                'route' => 'document-categories.manage',
                'icon'  => 'tags',
                'tone'  => 'slate',
                'title' => 'Categories',
                'text'  => 'How the document library is organised.',
                'stat'  => DocumentCategory::count(),
                'stat_label' => 'categories',
                'meta'  => 'library',
            ];
        }

        $groups[] = ['label' => 'Building blocks', 'tiles' => $parts];

        return view('documents-admin.index', compact('groups'));
    }

    /**
     * One list of everything published to staff, whatever table it lives in.
     *
     * Policies and company documents are stored separately for historical
     * reasons — and the policies carry hundreds of acknowledgement records that
     * are not worth moving — but an admin should not have to know that. Both
     * answer the same questions: what is it called, who is it for, what does it
     * need back, and how far along is it.
     */
    public function all(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $needs = $request->input('needs', 'all');

        $rows = collect();

        if (plan_allows('policies')) {
            $rows = $rows->merge(
                CompanyPolicy::withCount([
                    'acknowledgments',
                    'acknowledgments as done_count' => fn ($q) => $q->where('status', 'acknowledged'),
                ])->get()->map(fn ($p) => $this->row(
                    model: $p,
                    kind: 'Policy',
                    holds: trim((string) $p->content) !== '' ? 'Written here' : 'Uploaded file',
                    active: $p->status === 'active',
                    viewRoute: route('company-policies.show', $p),
                    editRoute: route('company-policies.edit', $p),
                ))
            );
        }

        if (plan_allows('esign') || plan_allows('documents')) {
            $rows = $rows->merge(
                CompanyDocument::withCount([
                    'acknowledgments',
                    'acknowledgments as done_count',
                ])->get()->map(fn ($d) => $this->row(
                    model: $d,
                    kind: 'Document',
                    holds: 'Uploaded file',
                    active: (bool) $d->is_active,
                    // A company document has no "show" page; its acknowledgement
                    // list is the nearest equivalent to a policy's view.
                    viewRoute: route('company-documents.acknowledgments', $d),
                    editRoute: route('company-documents.edit', $d),
                ))
            );
        }

        if ($search !== '') {
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['title'] . ' ' . $r['description']), mb_strtolower($search)));
        }

        if ($needs !== 'all') {
            $rows = $rows->filter(fn ($r) => $r['needs_key'] === $needs);
        }

        $rows = $rows->sortByDesc('created_at')->values();

        return view('documents-admin.all', compact('rows', 'search', 'needs'));
    }

    /** One shape both kinds of record map into. */
    private function row($model, string $kind, string $holds, bool $active, string $viewRoute, string $editRoute): array
    {
        $needsAck = (bool) $model->requires_acknowledgment;
        $needsSig = (bool) $model->requires_signature;

        $total = (int) ($model->acknowledgments_count ?? 0);
        $done = (int) ($model->done_count ?? 0);

        return [
            'id'          => $model->id,
            'kind'        => $kind,
            'title'       => (string) $model->title,
            'description' => (string) ($model->description ?? ''),
            'holds'       => $holds,
            'active'      => $active,
            'version'     => $model->version,
            'created_at'  => $model->created_at,
            'needs_key'   => $needsSig ? 'signature' : ($needsAck ? 'acknowledgement' : 'read'),
            'needs'       => $needsSig ? 'Signature' : ($needsAck ? 'Acknowledgement' : 'Read only'),
            'total'       => $total,
            'done'        => $done,
            'percent'     => $total > 0 ? (int) round($done / $total * 100) : null,
            'view'        => $viewRoute,
            'edit'        => $editRoute,
        ];
    }

    /**
     * One "Create" screen for everything in this hub. Asks what you are making,
     * then hands over to the builder for that kind.
     *
     * Admins used to have to know in advance whether the thing in their head was
     * a policy, a form, an uploaded file or a generated document, and go to a
     * different page for each. The names meant nothing until you knew the
     * system; this asks in terms of what you want to happen.
     */
    public function create()
    {
        $choices = [];

        if (plan_allows('hr_documents')) {
            $choices[] = [
                'route' => 'hr-documents.templates.create',
                'icon'  => 'file-signature',
                'tone'  => 'brand',
                'title' => 'A document that writes itself',
                'text'  => 'Build it once with your own wording, mark which boxes come from employee data, and it fills in for each person. Signed in the app.',
                'eg'    => 'Offer letter · Lateness review · Salary change letter · Return to work',
            ];
            // hr-documents.create needs a template id, so this goes to the page
            // where you pick one and generate from it.
            $choices[] = [
                'route' => 'hr-documents.index',
                'icon'  => 'file-plus',
                'tone'  => 'brand',
                'title' => 'One of those, for one person',
                'text'  => 'Pick a template you already built and generate it for a particular employee now.',
                'eg'    => 'A contract for the person who starts on Monday',
            ];
        }

        if (plan_allows('esign') || plan_allows('documents')) {
            $choices[] = [
                'route' => 'company-documents.create',
                'icon'  => 'upload',
                'tone'  => 'indigo',
                'title' => 'A file you already have',
                'text'  => plan_allows('esign')
                    ? 'Upload a Word or PDF file. Share it with staff, or place signature boxes on it and send it to be signed.'
                    : 'Upload a Word or PDF file and share it with staff, filed by category.',
                'eg'    => 'Employee handbook · A contract drafted by your lawyer',
            ];
        }

        if (plan_allows('policies')) {
            $choices[] = [
                'route' => 'company-policies.create',
                'icon'  => 'scroll-text',
                'tone'  => 'amber',
                'title' => 'Something people must read and agree to',
                'text'  => 'Write the text here. Assign it to people and track who has acknowledged it.',
                'eg'    => 'Sickness policy · Code of conduct · Expenses rules',
            ];
        }

        if (plan_allows('forms')) {
            $choices[] = [
                'route' => 'company-forms.index',
                'params' => ['new' => 1],
                'icon'  => 'clipboard-list',
                'tone'  => 'emerald',
                'title' => 'Questions you want answered',
                'text'  => 'Ask staff something and collect their replies, with reviewers and export.',
                'eg'    => 'Exit interview · Equipment request · Wellbeing check-in',
            ];
        }

        return view('documents-admin.create', compact('choices'));
    }
}
