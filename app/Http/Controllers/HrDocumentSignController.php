<?php

namespace App\Http\Controllers;

use App\Models\HrDocument;
use App\Models\HrDocumentSigner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Employee-facing signing of HR documents that were sent to them. Any
 * authenticated user may reach these, but every action is gated to the people
 * actually assigned as signers on the document.
 */
class HrDocumentSignController extends Controller
{
    /** The current user's documents awaiting (and recently completed) signature. */
    public function index(Request $request)
    {
        $uid   = auth()->id();
        $month = $request->input('month');   // YYYY-MM filter for the signed history

        $pending = HrDocumentSigner::with('document.employee')
            ->where('user_id', $uid)->whereNull('signed_at')
            ->latest()->get()
            ->filter(fn ($s) => $s->document !== null);

        $doneQuery = HrDocumentSigner::with('document.employee')
            ->where('user_id', $uid)->whereNotNull('signed_at');

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

        return view('hr-documents.to-sign', compact('pending', 'done', 'month'));
    }

    /** PDF of a document the current user is a signer on (preview or download). */
    public function pdf(HrDocument $document, Request $request)
    {
        abort_unless($document->signers()->where('user_id', auth()->id())->exists(), 403);

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $pdf = Pdf::loadView('hr-documents.pdf', ['document' => $document])->setPaper('a4', 'portrait');
        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';
        $name = Str::of($document->template_name . ' ' . optional($document->employee)->full_name)
            ->slug('_')->limit(60, '')->toString();

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.($name ?: 'hr-document').'.pdf"',
        ]);
    }

    /** Review-and-sign page for a document sent to the current user. */
    public function show(HrDocument $document)
    {
        $signer = $document->signers()->where('user_id', auth()->id())->first();
        abort_unless($signer, 403);

        $document->load('employee');

        return view('hr-documents.sign', compact('document', 'signer'));
    }

    /** Record the current user's signature(s). */
    public function store(Request $request, HrDocument $document)
    {
        $signer = $document->signers()
            ->where('user_id', auth()->id())->whereNull('signed_at')->first();
        abort_unless($signer, 403);

        $sigs = json_decode((string) $request->input('signatures'), true);
        $sigs = is_array($sigs) ? $sigs : [];

        foreach ($signer->field_ids as $fid) {
            if (empty($sigs[$fid]['image'])) {
                return back()->with('error', 'Please add your signature before submitting.');
            }
        }

        $data = $document->data ?? [];
        foreach ($signer->field_ids as $fid) {
            $data[$fid] = [
                'image' => $sigs[$fid]['image'],
                'name'  => $sigs[$fid]['name'] ?? auth()->user()->full_name,
                'date'  => $sigs[$fid]['date'] ?? now()->toDateString(),
            ];
        }
        $document->data = $data;
        $document->save();

        $signer->update(['signed_at' => now(), 'signed_ip' => $request->ip()]);

        $document->load('signers', 'creator');
        if ($document->fully_signed && $document->status !== 'completed') {
            $document->update(['status' => 'completed']);
        }

        if ($document->creator) {
            $document->creator->notify(new \App\Notifications\HrDocumentSigned($document, auth()->user()->full_name));
        }

        return redirect()->route('hr-documents.to-sign')
            ->with('success', 'Thank you — your signature has been recorded.');
    }
}
