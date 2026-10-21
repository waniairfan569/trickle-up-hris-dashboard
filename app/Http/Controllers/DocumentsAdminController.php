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
}
