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
            @if(!empty($ack->field_values))
                <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-3 dark:bg-slate-800 dark:border-slate-700">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Details you submitted</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($ack->field_values as $token => $value)
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-700">
                                <span class="font-bold text-slate-500 dark:text-slate-300">{{ trim($token, '[]') }}:</span>
                                <span class="font-semibold text-slate-800 dark:text-white">{{ $value }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            {{-- Fields the employee fills in before acknowledging --}}
            <div x-show="empFields.length" x-cloak class="rounded-xl border border-amber-200 bg-white p-4 dark:bg-slate-800 dark:border-amber-500/30 space-y-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-1.5"><i data-lucide="pencil" class="h-4 w-4 text-amber-500"></i> Fill in your details</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Complete these before acknowledging — they'll be recorded and filled into the document.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <template x-for="f in empFields" :key="f.token">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1" x-text="f.label"></label>
                            <input type="text" x-model="fieldValues[f.token]" @input="paintEmp()"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white"
                                   :placeholder="f.label">
                        </div>
                    </template>
                </div>
            </div>

            <form method="POST" action="{{ route('document-library.acknowledge', $document) }}" x-ref="ackForm" class="rounded-xl bg-amber-50 px-4 py-3 dark:bg-amber-500/10">
                @csrf
                <input type="hidden" name="employee_fields" :value="JSON.stringify(fieldValues)">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" @change="onAck($event)" class="mt-0.5 h-4 w-4 rounded border-amber-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">I acknowledge that I have read and understood this document.</span>
                </label>
                <p x-show="ackErr" x-cloak class="text-xs text-rose-600 mt-2 ml-6" x-text="ackErr"></p>
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
    window.__readCanFill = @json($canFill ?? false);

    function readDoc() {
        return {
            loading: true, error: '',
            canFill: window.__readCanFill || false,
            empFields: [],       // [{ token: '[Loan Amount]', label: 'Loan Amount' }]
            fieldValues: {},     // token => typed value
            ackErr: '',
            _idx: {}, _empLayers: {}, _empSeen: {}, _empOrder: [],

            init() { this.render(); },
            async render() {
                if (this._rendered) return; // render the pages only once
                this._rendered = true;
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
                        this._idx[n] = await this.buildIndex(page, vp, wrap);
                        this.paintTokens(n, window.__readTokens || {});   // profile + already-saved values
                        this.detectEmp(n);                                // find employee-fillable tokens
                    }
                    this.buildFieldList();
                    this.paintEmp();
                    this.loading = false;
                    if (window.lucide) lucide.createIcons();
                } catch (e) {
                    this.error = 'Could not render the document (' + (e && e.message ? e.message : e) + ').';
                    this.loading = false;
                }
            },

            // Per-page text index: runs in reading order + a char→run map, so a
            // token split across runs or wrapped to the next line still matches.
            async buildIndex(page, vp, container) {
                let tc; try { tc = await page.getTextContent(); } catch (e) { return { ord: [], full: '', map: [], container }; }
                const lib = window.pdfjsLib;
                const runs = tc.items.map((it) => {
                    const m = lib.Util.transform(vp.transform, it.transform);
                    return { str: it.str || '', x: m[4], y: m[5], h: Math.hypot(m[2], m[3]) || 11, w: (it.width || 0) * (vp.scale || 1) };
                }).filter((r) => r.str.length);
                const ord = runs.slice().sort((a, b) => (Math.round(a.y) - Math.round(b.y)) || (a.x - b.x));
                let full = ''; const map = [];
                ord.forEach((r, ri) => {
                    if (full.length) { full += ' '; map.push({ ri: -1, ci: -1 }); }
                    for (let ci = 0; ci < r.str.length; ci++) { full += r.str[ci]; map.push({ ri, ci }); }
                });
                return { ord, full, map, container };
            },

            mkBox(target, x, y, w, h, bg) {
                const el = document.createElement('div');
                el.style.cssText = `position:absolute;background:${bg};left:${x}px;top:${y}px;width:${w}px;height:${h}px;`;
                target.appendChild(el);
            },

            // White-out a [token] wherever it occurs and draw `val` (or a
            // highlighted blank when `highlight` and no value yet). A small pad
            // catches the [ ] bracket edges.
            matchDraw(idx, work, tok, val, target, highlight) {
                const { ord, map } = idx;
                const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+');
                const rx = new RegExp(esc(tok), 'g');
                const bg = (highlight && !val) ? '#fef9c3' : '#fff';
                let m, guard = 0;
                while ((m = rx.exec(work.full)) !== null && guard++ < 200) {
                    const S = m.index, E = m.index + m[0].length - 1;
                    const byRun = new Map();
                    for (let k = S; k <= E; k++) { const cc = map[k]; if (!cc || cc.ri < 0) continue; const g = byRun.get(cc.ri); if (!g) byRun.set(cc.ri, [cc.ci, cc.ci]); else { g[0] = Math.min(g[0], cc.ci); g[1] = Math.max(g[1], cc.ci); } }
                    let first = null;
                    const pad = Math.max(1.5, ord[byRun.keys().next().value].h * 0.22);
                    for (const [ri, [c0, c1]] of byRun) {
                        const r = ord[ri]; const L = r.str.length || 1;
                        const x0 = r.x + (c0 / L) * r.w, x1 = r.x + ((c1 + 1) / L) * r.w;
                        this.mkBox(target, x0 - pad, r.y - r.h, (x1 - x0) + pad * 2, r.h * 1.25, bg);
                        if (first === null) first = { x: x0 - pad, y: r.y - r.h, h: r.h };
                    }
                    if (first && (val || highlight)) {
                        const el = document.createElement('div');
                        el.textContent = val || '';
                        el.style.cssText = `position:absolute;background:${bg};color:#0f172a;white-space:nowrap;overflow:hidden;`
                            + `left:${first.x}px;top:${first.y}px;height:${first.h * 1.25}px;line-height:${first.h * 1.25}px;`
                            + `font-size:${first.h}px;font-family:Helvetica,Arial,sans-serif;padding:0 1px;`;
                        target.appendChild(el);
                    }
                    work.full = work.full.slice(0, m.index) + ' '.repeat(m[0].length) + work.full.slice(m.index + m[0].length);
                    rx.lastIndex = 0;
                }
            },

            // Overlay resolved token values (profile + already-saved).
            paintTokens(num, tokens) {
                const idx = this._idx[num];
                if (!idx || !idx.container || !Object.keys(tokens).length) return;
                const work = { full: idx.full };
                for (const [tok, val] of Object.entries(tokens)) {
                    this.matchDraw(idx, work, tok, String(val ?? ''), idx.container, false);
                }
            },

            // Bracket placeholders the profile can't answer and aren't signatures
            // become employee-fillable fields.
            detectEmp(num) {
                const idx = this._idx[num];
                if (!idx) return;
                const norm = (s) => s.toLowerCase().replace(/\s+/g, ' ').replace(/\[\s+/g, '[').replace(/\s+\]/g, ']').trim();
                const known = new Set(Object.keys(window.__readTokens || {}).map(norm));
                const rx = /\[[^\]\r\n]{1,60}\]/g;
                let m;
                while ((m = rx.exec(idx.full)) !== null) {
                    const raw = m[0], key = norm(raw);
                    if (known.has(key) || this._empSeen[key]) continue;
                    if (/signature|initial|sign here/i.test(raw)) continue;
                    this._empSeen[key] = true;
                    const label = raw.replace(/^\[|\]$/g, '').replace(/[_\s]+/g, ' ').trim();
                    this._empOrder.push({ token: raw, label: label || 'Field' });
                }
            },

            buildFieldList() {
                this.empFields = this._empOrder || [];
                const fv = {};
                this.empFields.forEach((f) => { fv[f.token] = this.fieldValues[f.token] || ''; });
                this.fieldValues = fv;
            },

            // Live overlay of the employee's typed values (yellow while blank).
            paintEmp() {
                for (const num of Object.keys(this._idx)) {
                    const idx = this._idx[num];
                    if (!idx || !idx.container) continue;
                    let layer = this._empLayers[num];
                    if (!layer) {
                        layer = document.createElement('div');
                        layer.style.cssText = 'position:absolute;inset:0;pointer-events:none;';
                        idx.container.appendChild(layer);
                        this._empLayers[num] = layer;
                    }
                    layer.innerHTML = '';
                    const work = { full: idx.full };
                    for (const f of this.empFields) {
                        this.matchDraw(idx, work, f.token, (this.fieldValues[f.token] || '').trim(), layer, true);
                    }
                }
            },

            fieldsComplete() {
                if (!this.canFill) return true;
                return this.empFields.every((f) => (this.fieldValues[f.token] || '').trim() !== '');
            },

            onAck(e) {
                if (!e.target.checked) return;
                if (!this.fieldsComplete()) {
                    e.target.checked = false;
                    this.ackErr = 'Please fill in all the fields above first.';
                    return;
                }
                e.target.disabled = true;
                this.$refs.ackForm.submit();
            },
        };
    }
</script>
@endsection
