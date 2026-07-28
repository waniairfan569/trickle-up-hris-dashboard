@extends('layouts.hr-app')

@section('title', $document->title)
@section('breadcrumb', 'Document Library')

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-5xl mx-auto space-y-4" x-data="readDoc()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('document-library.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> Document Library</a>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white mt-1 truncate">{{ $document->title }}</h1>
        </div>
        <a href="{{ route('document-library.download', $document) }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="download" class="h-4 w-4"></i> Download</a>
    </div>

    {{-- Acknowledgment panel --}}
    @if($document->requires_acknowledgment)
        @if($ack)
            <div class="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 dark:bg-emerald-500/10">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">You acknowledged this on {{ $ack->acknowledged_at->format('d M Y') }}.</span>
            </div>
        @else
            <form method="POST" action="{{ route('document-library.acknowledge', $document) }}" class="rounded-xl bg-amber-50 px-4 py-3 dark:bg-amber-500/10">
                @csrf
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" onchange="if(this.checked){ this.disabled=true; this.form.submit(); }" class="mt-0.5 h-4 w-4 rounded border-amber-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">I acknowledge that I have read and understood this document.</span>
                </label>
            </form>
        @endif
    @endif

    <div class="rounded-2xl border border-slate-200/80 shadow-sm bg-slate-100 dark:bg-slate-900 dark:border-slate-700 overflow-auto p-4" style="max-height: calc(100vh - 240px);">
        <div x-show="loading" class="py-20 text-center text-sm text-slate-400"><i data-lucide="loader" class="h-5 w-5 mx-auto mb-2 animate-spin"></i> Loading document…</div>
        <div x-show="error" x-cloak class="py-20 text-center text-sm text-rose-500" x-text="error"></div>
        <div id="pages" class="space-y-4"></div>
    </div>
</div>

<script src="https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
<script>if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';</script>
<script>
    window.__readFileUrl = "{{ $fileUrl ?? route('document-library.view', $document) }}";
    window.__readTokens = @json($tokens ?? []);

    function readDoc() {
        return {
            loading: true, error: '',
            init() { this.render(); },
            async render() {
                const lib = window.pdfjsLib;
                if (!lib) { this.error = 'PDF viewer failed to load.'; this.loading = false; return; }
                try {
                    const doc = await lib.getDocument(window.__readFileUrl).promise;
                    const host = document.getElementById('pages');
                    const wrapW = Math.min(820, host.clientWidth || 820);
                    for (let n = 1; n <= doc.numPages; n++) {
                        const page = await doc.getPage(n);
                        const vp = page.getViewport({ scale: wrapW / page.getViewport({ scale: 1 }).width });
                        const wrap = document.createElement('div');
                        wrap.style.cssText = `position:relative;width:${Math.round(vp.width)}px;margin:0 auto;background:#fff;box-shadow:0 1px 6px rgba(15,23,42,.12);`;
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.round(vp.width); canvas.height = Math.round(vp.height);
                        wrap.appendChild(canvas);
                        host.appendChild(wrap);
                        await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                        await this.overlayTokens(page, vp, wrap);
                    }
                    this.loading = false;
                    if (window.lucide) lucide.createIcons();
                } catch (e) {
                    this.error = 'Could not render the document (' + (e && e.message ? e.message : e) + ').';
                    this.loading = false;
                }
            },

            // Cover each literal [token] with its resolved value. Matches across
            // the whole page in reading order so a token that wraps still fills.
            async overlayTokens(page, vp, container) {
                const tokens = window.__readTokens || {};
                if (!Object.keys(tokens).length) return;
                const lib = window.pdfjsLib;
                let tc; try { tc = await page.getTextContent(); } catch (e) { return; }
                const runs = tc.items.map((it) => {
                    const m = lib.Util.transform(vp.transform, it.transform);
                    return { str: it.str || '', x: m[4], y: m[5], h: Math.hypot(m[2], m[3]) || 11, w: (it.width || 0) * (vp.scale || 1) };
                }).filter((r) => r.str.length);
                const ord = runs.slice().sort((a, b) => (Math.round(a.y) - Math.round(b.y)) || (a.x - b.x));
                // Map each char to its run + offset WITHIN that run, so we can
                // cover only the token's span (not the whole run — which would
                // erase the surrounding sentence text an inline token sits in).
                let full = ''; const map = [];
                ord.forEach((r, ri) => {
                    if (full.length) { full += ' '; map.push({ ri: -1, ci: -1 }); }
                    for (let ci = 0; ci < r.str.length; ci++) { full += r.str[ci]; map.push({ ri, ci }); }
                });
                const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+');
                const box = (x, y, w, h) => { const el = document.createElement('div'); el.style.cssText = `position:absolute;background:#fff;left:${x}px;top:${y}px;width:${w}px;height:${h}px;`; container.appendChild(el); return el; };
                for (const [tok, val] of Object.entries(tokens)) {
                    const rx = new RegExp(esc(tok), 'g');
                    let m, guard = 0;
                    while ((m = rx.exec(full)) !== null && guard++ < 200) {
                        const S = m.index, E = m.index + m[0].length - 1;
                        // Matched char range per run (proportional sub-position).
                        const byRun = new Map();
                        for (let k = S; k <= E; k++) { const { ri, ci } = map[k]; if (ri < 0) continue; const g = byRun.get(ri); if (!g) byRun.set(ri, [ci, ci]); else { g[0] = Math.min(g[0], ci); g[1] = Math.max(g[1], ci); } }
                        let first = null;
                        const pad = Math.max(1.5, ord[byRun.keys().next().value].h * 0.22);
                        for (const [ri, [c0, c1]] of byRun) {
                            const r = ord[ri]; const L = r.str.length || 1;
                            const x0 = r.x + (c0 / L) * r.w;
                            const x1 = r.x + ((c1 + 1) / L) * r.w;
                            box(x0 - pad, r.y - r.h, (x1 - x0) + pad * 2, r.h * 1.25); // white-out the token (+ small pad to catch [ ] edges)
                            if (first === null) first = { x: x0 - pad, y: r.y - r.h, h: r.h };
                        }
                        if (first) {
                            const el = document.createElement('div');
                            el.textContent = val;
                            el.style.cssText = 'position:absolute;background:#fff;color:#0f172a;white-space:nowrap;overflow:hidden;'
                                + `left:${first.x}px;top:${first.y}px;height:${first.h * 1.25}px;line-height:${first.h * 1.25}px;`
                                + `font-size:${first.h}px;font-family:Helvetica,Arial,sans-serif;padding:0 1px;`;
                            container.appendChild(el);
                        }
                        full = full.slice(0, m.index) + ' '.repeat(m[0].length) + full.slice(m.index + m[0].length);
                        rx.lastIndex = 0;
                    }
                }
            },
        };
    }
</script>
@endsection
