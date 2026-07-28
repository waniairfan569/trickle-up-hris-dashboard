@extends('layouts.hr-app')

@section('title', 'Document')
@section('breadcrumb', 'Documents')

@php
    $badge = [
        'in_progress' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'declined' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'cancelled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ][$documentRequest->status] ?? 'bg-slate-100 text-slate-600';
    $statusLabel = ['in_progress' => 'In progress', 'completed' => 'Completed', 'declined' => 'Declined', 'cancelled' => 'Cancelled'][$documentRequest->status] ?? ucfirst($documentRequest->status);
    $auth = auth()->user();
    $awaitingMe = $documentRequest->isAwaiting($auth);
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>
<script>
    window.__signedDataUrl = "{{ route('documents.signed-data', $documentRequest) }}";
    window.__signedName = @json(($documentRequest->template->name ?? 'document'));
</script>
<script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
<script src="https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
<script>if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';</script>

<div class="max-w-3xl mx-auto space-y-6" x-data="signedDownloader()">
    <div class="flex items-center gap-3">
        <a href="{{ auth()->user()->isAdmin() ? route('company-documents.admin') : route('document-library.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white truncate">{{ $documentRequest->template->name ?? 'Document' }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">For {{ $documentRequest->subject->full_name ?? '—' }} · sent by {{ $documentRequest->creator->full_name ?? '—' }}</p>
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">{{ $statusLabel }}</span>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i><span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Actions -->
    <div class="flex flex-wrap gap-2">
        @if($awaitingMe)
            <a href="{{ route('documents.sign', $documentRequest) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                <i data-lucide="pen-tool" class="h-4 w-4"></i> Sign now
            </a>
        @endif
        @if($documentRequest->status === 'completed')
            <button type="button" @click="viewSigned()" :disabled="busy" :class="busy && 'opacity-50'"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700">
                <template x-if="!busy"><i data-lucide="eye" class="h-4 w-4"></i></template>
                <template x-if="busy"><i data-lucide="loader" class="h-4 w-4 animate-spin"></i></template>
                <span x-text="busy ? 'Preparing…' : 'View signed document'"></span>
            </button>
            <button type="button" @click="download()" :disabled="busy" :class="busy && 'opacity-50'"
                    class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                <i data-lucide="file-down" class="h-4 w-4"></i>
                <span>Download signed PDF</span>
            </button>
        @endif
        <a href="{{ route('documents.file', $documentRequest) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300">
            <i data-lucide="file-text" class="h-4 w-4"></i> View original (blank)
        </a>
    </div>
    <p x-show="err" x-cloak class="text-sm text-rose-600" x-text="err"></p>

    <!-- Signers / progress -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/60"><span class="text-sm font-bold text-slate-700 dark:text-slate-200">Signers</span></div>
        @foreach($documentRequest->signers as $s)
            @php
                $isCurrent = $documentRequest->status === 'in_progress' && $documentRequest->currentSigner() && $documentRequest->currentSigner()->id === $s->id;
                $dot = $s->status === 'signed' ? 'bg-emerald-500' : ($s->status === 'declined' ? 'bg-rose-500' : ($isCurrent ? 'bg-amber-500' : 'bg-slate-300'));
            @endphp
            <div class="flex items-center gap-3 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-700">{{ $loop->iteration }}</span>
                <span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 dark:text-white truncate">{{ $s->user->full_name ?? 'Unknown' }} <span class="text-xs font-normal text-slate-400">· {{ $s->role_label }}</span></p>
                    <p class="text-xs text-slate-400">
                        @if($s->status === 'signed') Signed {{ optional($s->signed_at)->format('d M Y, H:i') }}
                        @elseif($s->status === 'declined') Declined {{ optional($s->signed_at)->format('d M Y, H:i') }}
                        @elseif($isCurrent) Awaiting signature
                        @else Waiting @endif
                    </p>
                </div>
                @if($s->status === 'signed' && $s->signature_image)
                    <img src="{{ $s->signature_image }}" alt="signature" class="h-8 max-w-[120px] object-contain rounded border border-slate-200 bg-white dark:border-slate-600">
                @endif
            </div>
        @endforeach
    </div>

    <!-- Audit trail -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/60"><span class="text-sm font-bold text-slate-700 dark:text-slate-200">Activity</span></div>
        <div class="px-6 py-4 space-y-3">
            @forelse($documentRequest->events as $e)
                <div class="flex items-start gap-3 text-sm">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-700"><i data-lucide="dot" class="h-4 w-4"></i></span>
                    <div>
                        <p class="text-slate-700 dark:text-slate-200">
                            <span class="font-semibold capitalize">{{ $e->action }}</span>
                            @if($e->user) by {{ $e->user->full_name }} @endif
                            @if($e->detail) <span class="text-slate-400">— {{ $e->detail }}</span> @endif
                        </p>
                        <p class="text-xs text-slate-400">{{ optional($e->created_at)->format('d M Y, H:i') }} @if($e->ip) · {{ $e->ip }} @endif</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    function signedDownloader() {
        return {
            busy: false,
            err: '',
            // Build the signed PDF (original + placed field values + signatures) and
            // return a blob URL. Shared by both View and Download.
            async buildSignedPdfUrl() {
                if (!window.PDFLib) throw new Error('PDF engine failed to load.');
                const data = await fetch(window.__signedDataUrl, { headers: { 'Accept': 'application/json' } }).then(r => r.json());
                const buf = await fetch(data.fileUrl).then(r => r.arrayBuffer());
                const { PDFDocument, StandardFonts, rgb } = window.PDFLib;
                const pdf = await PDFDocument.load(buf.slice(0));
                const font = await pdf.embedFont(StandardFonts.Helvetica);
                const pages = pdf.getPages();

                // Bake [token] values (e.g. [Full Name], [Amount]) into the PDF —
                // covering the literal placeholder text — so token-based documents
                // produce a correctly filled signed PDF. Signature tokens
                // ([Employee Signature] / [Sender's signature]) stamp the actual
                // signature IMAGE at their position instead of text.
                const sigTokens = data.signatureTokens || {};
                // Whether a signature was stamped at a token — if so, we skip the
                // bottom-of-page fallback signature so it isn't duplicated.
                let sigTokenStamped = false;
                if ((Object.keys(data.tokens || {}).length || Object.keys(sigTokens).length) && window.pdfjsLib) {
                    try {
                        // Embed each unique signature image once.
                        const embeddedSig = {};
                        for (const url of new Set(Object.values(sigTokens))) {
                            try { embeddedSig[url] = await pdf.embedPng(url); } catch (e) { /* skip bad image */ }
                        }
                        const jsDoc = await pdfjsLib.getDocument({ data: buf.slice(0) }).promise;
                        for (let n = 1; n <= jsDoc.numPages; n++) {
                            const plPage = pages[n - 1];
                            if (!plPage) continue;
                            const ph = plPage.getHeight();
                            const jpage = await jsDoc.getPage(n);
                            const vp = jpage.getViewport({ scale: 1 });
                            const tc = await jpage.getTextContent();
                            const runs = tc.items.map((it) => {
                                const m = pdfjsLib.Util.transform(vp.transform, it.transform);
                                return { str: it.str || '', x: m[4], y: m[5], h: Math.hypot(m[2], m[3]) || 11, w: (it.width || 0) * (vp.scale || 1) };
                            }).filter((r) => r.str.length);
                            // Match tokens across the WHOLE page in reading order,
                            // not per line — so a token that wraps to the next line
                            // (e.g. "[Employee" + "Signature]") is still found. A
                            // boundary space is inserted between runs and token
                            // whitespace is matched flexibly (\s+) to absorb the
                            // space introduced at a wrap point.
                            const ord = runs.slice().sort((a, b) => (Math.round(a.y) - Math.round(b.y)) || (a.x - b.x));
                            // Map each char to its run + offset WITHIN the run, so we
                            // white out only the token's span — not the whole run,
                            // which would erase the sentence an inline token sits in.
                            let full = ''; const map = [];
                            ord.forEach((r, ri) => {
                                if (full.length) { full += ' '; map.push({ ri: -1, ci: -1 }); }
                                for (let ci = 0; ci < r.str.length; ci++) { full += r.str[ci]; map.push({ ri, ci }); }
                            });
                            const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+');
                            const runMatch = (tok, draw) => {
                                const rx = new RegExp(esc(tok), 'g');
                                let m, guard = 0;
                                while ((m = rx.exec(full)) !== null && guard++ < 200) {
                                    const S = m.index, E = m.index + m[0].length - 1;
                                    const byRun = new Map();
                                    for (let k = S; k <= E; k++) { const { ri, ci } = map[k]; if (ri < 0) continue; const g = byRun.get(ri); if (!g) byRun.set(ri, [ci, ci]); else { g[0] = Math.min(g[0], ci); g[1] = Math.max(g[1], ci); } }
                                    let first = null;
                                    for (const [ri, [c0, c1]] of byRun) {
                                        const r = ord[ri]; const L = r.str.length || 1;
                                        const x0 = r.x + (c0 / L) * r.w, x1 = r.x + ((c1 + 1) / L) * r.w;
                                        plPage.drawRectangle({ x: x0, y: ph - r.y - r.h * 0.22, width: (x1 - x0) + 1, height: r.h * 1.3, color: rgb(1, 1, 1) });
                                        if (first === null) first = { x: x0, y: r.y, h: r.h };
                                    }
                                    if (first) draw(first);
                                    full = full.slice(0, m.index) + ' '.repeat(m[0].length) + full.slice(m.index + m[0].length);
                                    rx.lastIndex = 0;
                                }
                            };
                            // Text tokens.
                            for (const [tok, val] of Object.entries(data.tokens || {})) {
                                runMatch(tok, (a) => {
                                    plPage.drawText(String(val), { x: a.x, y: ph - a.y, size: a.h * 0.92, font, color: rgb(0.06, 0.06, 0.09) });
                                });
                            }
                            // Signature image tokens → stamp the actual signature image.
                            for (const [tok, url] of Object.entries(sigTokens)) {
                                const png = embeddedSig[url];
                                runMatch(tok, (a) => {
                                    if (!png) return;
                                    sigTokenStamped = true;
                                    const h = a.h * 2.8;                 // signature ~2.8× the line text
                                    const w = png.width * (h / png.height);
                                    plPage.drawImage(png, { x: a.x, y: ph - a.y - h * 0.18, width: w, height: h });
                                });
                            }
                        }
                    } catch (e) { /* token baking is best-effort */ }
                }

                for (const f of data.fields) {
                    // A signature already stamped at a token → skip the bottom-of-
                    // page fallback so the signature isn't duplicated.
                    if (f.lastPage && sigTokenStamped) continue;
                    // Fallback signatures are flagged lastPage; otherwise clamp to a valid page.
                    const pageNum = f.lastPage ? pages.length : Math.min(f.page || 1, pages.length);
                    const page = pages[pageNum - 1];
                    if (!page) continue;
                    const pw = page.getWidth(), ph = page.getHeight();
                    if ((f.type === 'signature' || f.type === 'initials')) {
                        if (!f.image) continue;
                        const png = await pdf.embedPng(f.image);
                        const boxW = f.w * pw, boxH = (f.h || 0.05) * ph;
                        const scale = Math.min(boxW / png.width, boxH / png.height);
                        const w = png.width * scale, h = png.height * scale;
                        const topY = ph - f.y * ph;
                        page.drawImage(png, { x: f.x * pw, y: topY - h, width: w, height: h });
                        if (f.name) {
                            const ns = Math.max(6, Math.min(9, boxH * 0.32));
                            page.drawText(String(f.name), { x: f.x * pw, y: topY - h - ns - 1, size: ns, font, color: rgb(0.45, 0.45, 0.5) });
                        }
                        continue;
                    }
                    if (!f.value) continue;
                    const size = Math.max(8, Math.min(13, (f.h || 0.03) * ph * 0.7));
                    page.drawText(String(f.value), { x: f.x * pw + 2, y: ph - f.y * ph - size, size, font, color: rgb(0.1, 0.1, 0.12) });
                }
                const out = await pdf.save();
                return URL.createObjectURL(new Blob([out], { type: 'application/pdf' }));
            },
            async viewSigned() {
                if (this.busy) return;
                this.busy = true; this.err = '';
                // Open the tab synchronously so the browser doesn't block the popup,
                // then point it at the generated PDF once ready.
                const tab = window.open('', '_blank');
                try {
                    const url = await this.buildSignedPdfUrl();
                    if (tab) { tab.location = url; } else { window.location = url; }
                    setTimeout(() => URL.revokeObjectURL(url), 60000);
                } catch (e) {
                    if (tab) tab.close();
                    this.err = (e && e.message) ? e.message : 'Could not build the signed PDF.';
                } finally { this.busy = false; }
            },
            async download() {
                if (this.busy) return;
                this.busy = true; this.err = '';
                try {
                    const url = await this.buildSignedPdfUrl();
                    const a = document.createElement('a');
                    a.href = url; a.download = (window.__signedName || 'document').replace(/[^\w.\- ]+/g, '') + ' — signed.pdf';
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 10000);
                } catch (e) {
                    this.err = (e && e.message) ? e.message : 'Could not build the signed PDF.';
                } finally { this.busy = false; }
            },
        };
    }
</script>
@endsection
