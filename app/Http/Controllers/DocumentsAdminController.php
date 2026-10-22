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

        // --- Built per employee from a template ---
        if (plan_allows('hr_documents')) {
            $groups[] = ['label' => 'Generated per employee', 'tiles' => [[
                'route' => 'hr-documents.index',
                'icon'  => 'file-signature',
                'tone'  => 'brand',
                'title' => 'HR documents',
                'text'  => 'Templates that fill themselves from your own data, generate per employee and route for signature.',
                'stat'  => HrDocumentTemplate::where('is_active', true)->count(),
                'stat_label' => 'templates',
                'meta'  => HrDocument::count() . ' generated',
            ]]];
        }

        // --- Published to staff ---
        //
        // Policies and company documents used to sit in different groups here,
        // one under "sent for signature" and the other under "published to
        // staff", as though they were different kinds of thing. They are not: a
        // policy can require a signature too, and an uploaded document is just
        // as published as a policy is. The only real difference is whether you
        // typed the words or uploaded a file, which is a detail of making one,
        // not a reason to split the hub.
        //
        // So one tile, opening the list that already shows both. The specialist
        // pages stay — they hold archiving, categories, signature placement and
        // restore — and hang off this tile as secondary links.
        $canWrite = plan_allows('policies');
        $canUpload = plan_allows('esign') || plan_allows('documents');

        if ($canWrite || $canUpload) {
            $links = [];

            if ($canWrite) {
                $links[] = ['route' => 'company-policies.index', 'label' => 'Policies'];
            }

            // These two are gated on "esign" by EnforcePlanFeatures, not on
            // "documents" — so ask for exactly that, or a documents-only plan
            // would be shown links that answer 402.
            if (plan_allows('esign')) {
                $links[] = ['route' => 'company-documents.admin', 'label' => 'Document library'];
                $links[] = ['route' => 'company-documents.archived', 'label' => 'Archived'];
            }

            // Categories is not listed here — it is a building block, and has
            // its own tile further down with the letterheads and signatures.

            $groups[] = ['label' => 'Published to staff', 'tiles' => [[
                'route' => 'documents-admin.all',
                'icon'  => 'megaphone',
                'tone'  => 'amber',
                'title' => 'Everything published',
                'text'  => $this->publishedBlurb($canWrite, $canUpload),
                'stat'  => ($canWrite ? CompanyPolicy::count() : 0) + ($canUpload ? CompanyDocument::count() : 0),
                'stat_label' => 'items',
                'meta'  => 'read, acknowledge or sign',
                'links' => $links,
            ]]];
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

    /** What the published tile says, given what this plan can actually do. */
    private function publishedBlurb(bool $canWrite, bool $canUpload): string
    {
        return match (true) {
            $canWrite && $canUpload => 'Policies you write and files you upload, together — who it went to, what it needs back, and how many have done it.',
            $canWrite => 'Policy text staff must read and acknowledge, with assignment and tracking.',
            default => 'Files you upload and share with staff, with views and acknowledgements recorded.',
        };
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
                CompanyPolicy::get()->map(fn ($p) => $this->row(
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
            // The pages that manage an uploaded file are gated on "esign", while
            // the file itself belongs to "documents" — so a plan can have these
            // published and still not be allowed to open them. List them either
            // way; offer the links only where they would not answer 402.
            $canManage = plan_allows('esign');

            $rows = $rows->merge(
                CompanyDocument::get()->map(fn ($d) => $this->row(
                    model: $d,
                    kind: 'Document',
                    holds: 'Uploaded file',
                    active: (bool) $d->is_active,
                    // A company document has no "show" page; its acknowledgement
                    // list is the nearest equivalent to a policy's view.
                    viewRoute: $canManage ? route('company-documents.acknowledgments', $d) : null,
                    editRoute: $canManage ? route('company-documents.edit', $d) : null,
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

    /**
     * One form for publishing something to staff, whichever table it ends up in.
     *
     * It posts straight to the existing policy or document endpoint — whichever
     * matches what you chose — so the real validation and file handling stay in
     * one place rather than being reimplemented here and drifting.
     *
     * Where it lands follows from the content: typed text can only be stored by
     * a policy, an uploaded file is better handled by a document. The admin
     * never has to make that distinction themselves.
     */
    public function newDocument()
    {
        return view('documents-admin.new', [
            'policyCategories' => ['hr', 'it', 'finance', 'legal', 'health_safety', 'general'],
            'documentCategories' => DocumentCategory::orderBy('sort_order')->orderBy('name')->get(),
            'canWrite' => plan_allows('policies'),
            'canUpload' => plan_allows('esign') || plan_allows('documents'),
        ]);
    }

    /** One shape both kinds of record map into. */
    private function row($model, string $kind, string $holds, bool $active, ?string $viewRoute, ?string $editRoute): array
    {
        $needsAck = (bool) $model->requires_acknowledgment;
        $needsSig = (bool) $model->requires_signature;

        // Both kinds answer these the same way now. A policy counts the rows it
        // created per assignee; a document counts its audience, because its rows
        // only appear once someone has actually acknowledged.
        $total = $model->acknowledgementExpected();
        $done = $model->acknowledgedCount();

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

        // Publishing to staff is one form now — write it or upload it, then tick
        // whether you need an acknowledgement or a signature.
        if (plan_allows('policies') || plan_allows('esign') || plan_allows('documents')) {
            $choices[] = [
                'route' => 'documents-admin.new',
                'icon'  => 'megaphone',
                'tone'  => 'amber',
                'title' => 'Something to publish to staff',
                'text'  => 'Write it here or upload a file, choose who it goes to, and tick whether people must acknowledge it or sign it.',
                'eg'    => 'Sickness policy · Employee handbook · Code of conduct · A contract from your lawyer',
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
