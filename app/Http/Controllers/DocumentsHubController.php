<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use App\Models\DocumentAcknowledgment;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\HrDocumentSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The employee "Documents" hub — one page consolidating the former
 * Document Library, To Sign, My Policies and My Forms into tabs.
 * Each tab keeps its own data source; this controller just gathers them all.
 */
class DocumentsHubController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Which tab to open first (all | sign | policies | forms).
        $tab = in_array($request->query('tab'), ['all', 'sign', 'policies', 'forms'], true)
            ? $request->query('tab')
            : 'all';

        // ── All: Document Library ─────────────────────────────────────────
        $documents = CompanyDocument::active()
            ->accessibleBy($user)
            ->with('category')
            ->latest()
            ->get();

        $categories = DocumentCategory::orderBy('sort_order')->get()
            ->filter(fn ($c) => $documents->where('category_id', $c->id)->isNotEmpty())
            ->values();

        $docAcks = DocumentAcknowledgment::where('user_id', $user->id)
            ->whereIn('document_id', $documents->pluck('id'))
            ->get()->keyBy('document_id');

        // ── To Sign: e-sign document requests + HR documents ──────────────
        $toSign = DocumentRequest::with(['template', 'subject'])
            ->where('status', 'in_progress')
            ->whereHas('signers', fn ($s) => $s->where('user_id', $user->id)->where('status', 'pending'))
            ->get()
            ->filter(fn ($r) => $r->isAwaiting($user))
            ->values();

        $hrEnabled = plan_allows('hr_documents');
        $pending = collect();
        $done = collect();
        $month = $request->input('month');
        if ($hrEnabled) {
            $pending = HrDocumentSigner::with('document.employee')
                ->where('user_id', $user->id)->whereNull('signed_at')
                ->latest()->get()
                ->filter(fn ($s) => $s->document !== null);

            $doneQuery = HrDocumentSigner::with('document.employee')
                ->where('user_id', $user->id)->whereNotNull('signed_at');
            if ($month) {
                try {
                    $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                    $doneQuery->whereBetween('signed_at', [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()]);
                } catch (\Throwable $e) {
                    $month = null;
                }
            }
            $done = $doneQuery->latest('signed_at')->limit(100)->get()
                ->filter(fn ($s) => $s->document !== null);
        }

        // ── Policies ──────────────────────────────────────────────────────
        $policyAcks = $user->policyAcknowledgments()
            ->with('policy')
            ->get()
            ->filter(fn ($a) => $a->policy && $a->policy->status === 'active')
            ->sortByDesc('id')
            ->values();
        $policyPendingCount = $policyAcks->where('status', '!=', 'acknowledged')->count();

        // ── Forms ─────────────────────────────────────────────────────────
        $formsEnabled = plan_allows('forms');
        $submissions = collect();
        $periods = [];
        $selectedPeriod = null;
        if ($formsEnabled) {
            $all = $user->formSubmissions()
                ->with(['form', 'form.fields', 'reviewer', 'responses'])
                ->get()
                ->filter(fn ($s) => $s->form && $s->form->status !== 'draft')
                ->sortByDesc('id')
                ->values();

            $periods = $all->pluck('period')->filter()->unique()->sortDesc()->values()->all();
            $selectedPeriod = $request->get('period');
            if ($selectedPeriod && $selectedPeriod !== 'all' && !in_array($selectedPeriod, $periods, true)) {
                $selectedPeriod = null;
            }
            $submissions = ($selectedPeriod && $selectedPeriod !== 'all')
                ? $all->where('period', $selectedPeriod)->values()
                : $all;

            // Seeing the list clears the "new forms" sidebar badge.
            $user->forceFill(['forms_last_seen_at' => now()])->saveQuietly();
        }

        // Tab counts (things needing attention).
        $counts = [
            'sign' => $toSign->count() + $pending->count(),
            'policies' => $policyPendingCount,
            'forms' => $submissions->filter(fn ($s) => $s->status !== 'submitted')->count(),
        ];

        return view('employee.documents-hub.index', compact(
            'tab', 'counts',
            'documents', 'categories', 'docAcks',
            'toSign', 'pending', 'done', 'month', 'hrEnabled',
            'policyAcks', 'policyPendingCount',
            'submissions', 'periods', 'selectedPeriod', 'formsEnabled'
        ));
    }
}
