<?php

namespace App\Http\Controllers;

use App\Models\HrDocument;
use App\Models\HrDocumentSigner;
use Illuminate\Http\Request;

/**
 * Employee-facing signing of HR documents that were sent to them. Any
 * authenticated user may reach these, but every action is gated to the people
 * actually assigned as signers on the document.
 */
class HrDocumentSignController extends Controller
{
    /** The current user's documents awaiting (and recently completed) signature. */
    public function index()
    {
        $uid = auth()->id();

        $pending = HrDocumentSigner::with('document.employee')
            ->where('user_id', $uid)->whereNull('signed_at')
            ->latest()->get()
            ->filter(fn ($s) => $s->document !== null);

        $done = HrDocumentSigner::with('document.employee')
            ->where('user_id', $uid)->whereNotNull('signed_at')
            ->latest('signed_at')->limit(30)->get()
            ->filter(fn ($s) => $s->document !== null);

        return view('hr-documents.to-sign', compact('pending', 'done'));
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
