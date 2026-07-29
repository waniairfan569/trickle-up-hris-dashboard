@extends('layouts.hr-app')

@section('title', 'Sign document')
@section('breadcrumb', 'Documents')

@section('content')
<style>[x-cloak]{display:none!important} .sigpad{touch-action:none}</style>

@php
    $boxOpts = $myBoxes->values()->map(fn ($b, $i) => [
        'idx' => $i, 'page' => (int) $b->page, 'x' => (float) $b->pos_x, 'y' => (float) $b->pos_y,
        'w' => (float) $b->width, 'h' => (float) $b->height, 'label' => $b->label, 'type' => $b->field_type,
    ])->all();
@endphp
<script>
    window.__signFileUrl = "{{ route('documents.file', $documentRequest) }}";
    window.__signBoxes = @json($boxOpts);
    window.__docTokens = @json($tokens ?? []);
    window.__autoSignLastPage = @json($autoSignLastPage ?? false);
    window.__mySigTokens = @json($sigSpellings ?? []);
    window.__allSigTokens = @json($allSigSpellings ?? []);
    window.__canFillFields = @json($canFillFields ?? false);
    window.__savedSignatures = @json(($savedSignatures ?? collect())->map(fn ($s) => ['name' => $s->name, 'image' => $s->image_data]));
    window.__filledFields = @json($filledFields ?? []);
</script>
<script src="https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
<script>if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';</script>

<div class="max-w-5xl mx-auto space-y-5" x-data="signView()">
    <div class="flex items-center gap-3">
        <a href="{{ route('documents.show', $documentRequest) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $documentRequest->template->name ?? 'Document' }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">For {{ $documentRequest->subject->full_name ?? '—' }} · you're signing as {{ $signer->role_label }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">
        <!-- Document -->
        <div class="min-w-0" x-ref="pdfWrap">
            <div x-show="pdfLoading" class="py-20 text-center text-sm text-slate-400"><i data-lucide="loader" class="h-5 w-5 mx-auto mb-2 animate-spin"></i> Loading document…</div>
            <div x-show="!pdfReady && !pdfLoading" class="py-20 text-center text-sm text-slate-500" x-text="pdfError"></div>
            <div x-show="pdfReady" class="space-y-4 overflow-x-auto">
                <template x-for="pg in pages" :key="'pg' + pg.num">
                    <div class="relative mx-auto rounded-lg border border-slate-200 shadow-sm bg-white dark:border-slate-700" :style="`width:${pg.width}px;height:${pg.height}px`" :data-page="pg.num">
                        <canvas :id="'signcanvas-' + pg.num" class="block rounded-lg"></canvas>
                        <!-- Placed text fields, pre-filled from the signer's profile -->
                        <template x-for="ff in filledOnPage(pg.num)" :key="'ff' + ff.i">
                            <div class="absolute bg-white text-slate-900 font-medium pointer-events-none" :style="filledStyle(ff, pg)" x-text="ff.value"></div>
                        </template>
                        <template x-for="b in boxesOnPage(pg.num)" :key="'b' + b.idx">
                            <button type="button" @click="openSign()" class="absolute rounded border-2 flex items-center justify-center text-[10px] font-bold uppercase tracking-wide bg-white/70 hover:bg-brand-50"
                                    :class="signature ? 'border-emerald-400' : 'border-brand-500 border-dashed text-brand-700'" :style="boxStyle(b, pg)">
                                <template x-if="signature"><img :src="signature" class="max-h-full max-w-full object-contain p-0.5"></template>
                                <template x-if="!signature"><span>Click to sign <span class="text-rose-500">*</span></span></template>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Action panel -->
        <aside class="lg:sticky lg:top-4 self-start space-y-4">
            <!-- Fields the employee fills in -->
            <div x-show="canFill && empFields.length" x-cloak class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 dark:bg-slate-800 dark:border-amber-500/30 space-y-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-1.5"><i data-lucide="pencil" class="h-4 w-4 text-amber-500"></i> Fill in your details</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Complete these before signing. They'll be filled into the document.</p>
                </div>
                <template x-for="f in empFields" :key="f.token">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1" x-text="f.label"></label>
                        <input type="text" x-model="fieldValues[f.token]" @input="paintEmp()"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white"
                               :placeholder="f.label">
                    </div>
                </template>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700 space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Sign this document</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" x-show="boxes.length">Click a <span class="font-semibold text-brand-600">“Click to sign”</span> box on the document, or add your signature here.</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" x-show="!boxes.length">There are no signature fields assigned to you — adding your signature records your approval.</p>
                </div>

                <div x-show="signature" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-2 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                    <img :src="signature" class="h-12 mx-auto object-contain">
                </div>

                <button type="button" @click="openSign()" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                    <i data-lucide="pen-tool" class="h-4 w-4"></i> <span x-text="signature ? 'Change signature' : 'Add your signature'"></span>
                </button>

                <form method="POST" action="{{ route('documents.sign.store', $documentRequest) }}" @submit="onSubmit($event)">
                    @csrf
                    <input type="hidden" name="signature" :value="signature">
                    <input type="hidden" name="employee_fields" :value="JSON.stringify(fieldValues)">
                    <p x-show="submitErr" x-cloak class="text-xs text-rose-600 mb-2" x-text="submitErr"></p>
                    <button type="submit" :disabled="!signature || !fieldsComplete()" :class="(!signature || !fieldsComplete()) && 'opacity-50 cursor-not-allowed'"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                        <i data-lucide="check" class="h-4 w-4"></i> Sign &amp; submit
                    </button>
                </form>
                <p class="text-[11px] text-slate-400">By signing, you agree this is your electronic signature. Your name, time and IP are recorded.</p>
            </div>

            <form method="POST" action="{{ route('documents.decline', $documentRequest) }}" onsubmit="return confirm('Decline to sign this document? This cannot be undone.');">
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-rose-200 px-4 py-2.5 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:bg-slate-800 dark:border-rose-500/30">
                    <i data-lucide="x-circle" class="h-4 w-4"></i> Decline
                </button>
            </form>
        </aside>
    </div>

    <!-- Signature modal -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeModal()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Add your signature</h2>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="flex gap-1 px-6 pt-3 border-b border-slate-100 dark:border-slate-700/60">
                <button type="button" @click="modalMode = 'draw'" class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 -mb-px" :class="modalMode === 'draw' ? 'border-slate-800 text-slate-800 dark:border-white dark:text-white' : 'border-transparent text-slate-400'"><i data-lucide="pen-tool" class="h-4 w-4"></i> Draw</button>
                <button type="button" @click="modalMode = 'type'" class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 -mb-px" :class="modalMode === 'type' ? 'border-slate-800 text-slate-800 dark:border-white dark:text-white' : 'border-transparent text-slate-400'"><i data-lucide="keyboard" class="h-4 w-4"></i> Type</button>
                <button type="button" x-show="savedSignatures.length" @click="modalMode = 'saved'" class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 -mb-px" :class="modalMode === 'saved' ? 'border-slate-800 text-slate-800 dark:border-white dark:text-white' : 'border-transparent text-slate-400'"><i data-lucide="bookmark" class="h-4 w-4"></i> Saved</button>
            </div>
            <div class="p-6">
                <div x-show="modalMode === 'saved'" x-cloak>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Pick a saved signature to use it.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[220px] overflow-y-auto">
                        <template x-for="(s, i) in savedSignatures" :key="i">
                            <button type="button" @click="useSaved(s.image)" class="rounded-xl border border-slate-200 dark:border-slate-600 p-2 hover:border-brand-400 hover:bg-brand-50/40 dark:hover:bg-brand-500/10 flex flex-col items-center gap-1">
                                <img :src="s.image" class="h-16 max-w-full object-contain">
                                <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 truncate w-full text-center" x-text="s.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div x-show="modalMode === 'draw'" class="rounded-xl border border-slate-200 dark:border-slate-600 relative">
                    <canvas x-ref="pad" width="600" height="200" class="sigpad w-full bg-white rounded-xl"></canvas>
                    <button type="button" @click="clearPad()" class="absolute bottom-2 left-2 text-slate-400 hover:text-rose-500"><i data-lucide="x" class="h-4 w-4"></i></button>
                    <div class="absolute bottom-3 left-10 right-6 border-b border-slate-300 pointer-events-none"></div>
                </div>
                <div x-show="modalMode === 'type'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-600 relative">
                    <div class="h-[200px] flex items-center px-6">
                        <input type="text" x-model="typed" placeholder="Type your name" class="w-full bg-transparent border-0 focus:ring-0 text-4xl text-slate-900 dark:text-white outline-none" :style="`font-family:${fonts[fontIndex]};`">
                    </div>
                    <button type="button" @click="typed = ''" class="absolute bottom-2 left-2 text-slate-400 hover:text-rose-500"><i data-lucide="x" class="h-4 w-4"></i></button>
                    <div class="absolute bottom-3 left-10 right-40 border-b border-slate-300 pointer-events-none"></div>
                    <button type="button" @click="fontIndex = (fontIndex + 1) % fonts.length" class="absolute bottom-2 right-3 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800"><i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Change font</button>
                </div>
            </div>
            <div x-show="modalMode !== 'saved'" class="flex items-center justify-between px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                <span class="text-xs text-slate-500 dark:text-slate-400">I understand this is a legal representation of my signature.</span>
                <button type="button" @click="insert()" :disabled="!canInsert()" :class="canInsert() ? 'bg-brand-600 text-slate-900 hover:bg-brand-700' : 'bg-slate-200 text-slate-400 cursor-not-allowed dark:bg-slate-700'" class="inline-flex items-center gap-1.5 rounded-xl px-6 py-2.5 text-sm font-bold shadow-sm">Insert</button>
            </div>
        </div>
    </div>
</div>

<script>
    function signView() {
        let __pdfPages = [];
        return {
            pages: [],
            pdfReady: false,
            pdfLoading: false,
            pdfError: '',
            boxes: window.__signBoxes || [],
            autoSignLastPage: window.__autoSignLastPage || false,
            filledFields: window.__filledFields || [],
            savedSignatures: window.__savedSignatures || [],
            signature: '',
            submitErr: '',
            // Employee-fillable fields (bracket tokens the profile can't answer).
            canFill: window.__canFillFields || false,
            empFields: [],       // [{ token: '[Loan Amount]', label: 'Loan Amount' }]
            fieldValues: {},     // token => typed value
            _idx: {},            // per-page text index for overlays
            _empLayers: {},      // per-page DOM layer for live employee overlays
            // modal
            modalOpen: false,
            modalMode: 'draw',
            typed: '',
            fontIndex: 0,
            hasInk: false,
            fonts: ['"Brush Script MT", "Segoe Script", cursive', '"Lucida Handwriting", "Segoe Script", cursive', '"Comic Sans MS", cursive', 'Georgia, serif'],

            init() { this.loadPdf(); },

            boxesOnPage(n) { return this.boxes.filter(b => b.page === n); },
            boxStyle(b, pg) {
                return `left:${b.x * pg.width}px; top:${b.y * pg.height}px; width:${b.w * pg.width}px; height:${(b.h || 0.04) * pg.height}px;`;
            },
            filledOnPage(n) { return this.filledFields.map((f, i) => ({ ...f, i })).filter(f => f.page === n); },
            filledStyle(ff, pg) {
                const h = (ff.h || 0.03) * pg.height;
                const size = Math.max(9, Math.min(14, h * 0.7));
                return `left:${ff.x * pg.width}px; top:${ff.y * pg.height}px; width:${ff.w * pg.width}px; height:${h}px;`
                    + `font-size:${size}px; line-height:${h}px; overflow:hidden; white-space:nowrap;`;
            },

            async loadPdf() {
                if (this._rendering) return;   // never render the same canvases twice at once
                this._rendering = true;
                this.pdfLoading = true; this.pdfError = ''; this.pages = []; this.pdfReady = false; __pdfPages = [];
                this._idx = {}; this._empLayers = {}; this._empSeen = {}; this._empOrder = [];
                try {
                    const lib = window.pdfjsLib;
                    if (!lib) { this.pdfError = 'PDF viewer failed to load.'; this.pdfLoading = false; return; }
                    const doc = await lib.getDocument(window.__signFileUrl).promise;
                    const targetW = Math.min(640, (this.$refs.pdfWrap && this.$refs.pdfWrap.clientWidth) || 640);
                    const meta = [];
                    for (let n = 1; n <= doc.numPages; n++) {
                        const page = await doc.getPage(n);
                        const vp = page.getViewport({ scale: targetW / page.getViewport({ scale: 1 }).width });
                        __pdfPages.push({ page, vp });
                        meta.push({ num: n, width: Math.round(vp.width), height: Math.round(vp.height) });
                    }
                    this.pages = meta; this.pdfReady = true; this.pdfLoading = false;

                    // No box placed → no on-document "Click to sign" box. The
                    // signer uses the "Add your signature" button in the side
                    // panel; the signature is stamped at its token (or, for a
                    // token-less document, at the bottom of the last page) when
                    // the final PDF is built.

                    await this.$nextTick();
                    for (let i = 0; i < meta.length; i++) {
                        const num = meta[i].num;
                        const c = document.getElementById('signcanvas-' + num);
                        if (!c) continue;
                        c.width = meta[i].width; c.height = meta[i].height;
                        await __pdfPages[i].page.render({ canvasContext: c.getContext('2d'), viewport: __pdfPages[i].vp }).promise;
                        this._idx[num] = await this.buildIndex(__pdfPages[i].page, __pdfPages[i].vp, num);
                        this.paintTokens(num, window.__docTokens || {});  // profile + already-saved values
                        this.detectEmp(num);                              // find employee-fillable tokens
                    }
                    this.buildFieldList();
                    this.paintEmp();
                    if (window.lucide) lucide.createIcons();
                } catch (e) {
                    this.pdfReady = false; this.pdfLoading = false;
                    this.pdfError = 'Could not render the document (' + (e && e.message ? e.message : e) + ').';
                } finally {
                    this._rendering = false;
                }
            },

            // Build a per-page text index (runs in reading order + a char→run map)
            // so tokens split across runs or wrapped to the next line still match.
            async buildIndex(page, vp, num) {
                const container = document.querySelector(`[data-page="${num}"]`);
                let tc;
                try { tc = await page.getTextContent(); } catch (e) { return { ord: [], full: '', map: [], container }; }
                const lib = window.pdfjsLib;
                const runs = tc.items.map((it) => {
                    const m = lib.Util.transform(vp.transform, it.transform);
                    const h = Math.hypot(m[2], m[3]) || 11;
                    return { str: it.str || '', x: m[4], y: m[5], h, w: (it.width || 0) * (vp.scale || 1) };
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

            // White-out a [token] wherever it occurs on the page and draw `val`
            // (or a highlighted blank when `highlight` and no value yet).
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
                    for (const [ri, [c0, c1]] of byRun) {
                        const r = ord[ri]; const L = r.str.length || 1;
                        const x0 = r.x + (c0 / L) * r.w, x1 = r.x + ((c1 + 1) / L) * r.w;
                        this.mkBox(target, x0, r.y - r.h, (x1 - x0) + 1, r.h * 1.25, bg);
                        if (first === null) first = { x: x0, y: r.y - r.h, h: r.h };
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

            // Overlay resolved token values (profile + already-saved) so the signer
            // sees the filled contract instead of [candidate]/[job]/etc.
            paintTokens(num, tokens) {
                const idx = this._idx[num];
                if (!idx || !idx.container || !Object.keys(tokens).length) return;
                const work = { full: idx.full };
                for (const [tok, val] of Object.entries(tokens)) {
                    this.matchDraw(idx, work, tok, String(val ?? ''), idx.container, false);
                }
            },

            // Find bracket placeholders the profile can't answer and aren't
            // signatures — these become employee-fillable fields.
            detectEmp(num) {
                const idx = this._idx[num];
                if (!idx) return;
                const norm = (s) => s.toLowerCase().replace(/\s+/g, ' ').replace(/\[\s+/g, '[').replace(/\s+\]/g, ']').trim();
                const known = new Set(Object.keys(window.__docTokens || {}).map(norm));
                (window.__allSigTokens || []).forEach((t) => known.add(norm(t)));
                const rx = /\[[^\]\r\n]{1,60}\]/g;
                let m;
                while ((m = rx.exec(idx.full)) !== null) {
                    const raw = m[0];
                    const key = norm(raw);
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

            // Live overlay of the employee's typed values (yellow highlight while blank).
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

            onSubmit(e) {
                if (!this.signature) { this.submitErr = 'Please add your signature first.'; e.preventDefault(); return; }
                if (!this.fieldsComplete()) { this.submitErr = 'Please fill in all the required fields first.'; e.preventDefault(); return; }
            },


            // ---- signature modal ----
            openSign() {
                this.modalMode = 'draw'; this.typed = ''; this.hasInk = false; this.modalOpen = true;
                this.$nextTick(() => { this.setupPad(); if (window.lucide) lucide.createIcons(); });
            },
            closeModal() { this.modalOpen = false; },
            canInsert() { return this.modalMode === 'draw' ? this.hasInk : this.typed.trim() !== ''; },

            setupPad() {
                const c = this.$refs.pad; if (!c) return;
                const ctx = c.getContext('2d');
                ctx.clearRect(0, 0, c.width, c.height);
                ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
                this.hasInk = false;
                let drawing = false;
                const pos = (e) => { const r = c.getBoundingClientRect(); return { x: (e.clientX - r.left) * (c.width / r.width), y: (e.clientY - r.top) * (c.height / r.height) }; };
                c.onpointerdown = (e) => { drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); c.setPointerCapture(e.pointerId); };
                c.onpointermove = (e) => { if (!drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); this.hasInk = true; };
                c.onpointerup = () => { drawing = false; };
                c.onpointerleave = () => { drawing = false; };
            },
            clearPad() { const c = this.$refs.pad; if (c) { c.getContext('2d').clearRect(0, 0, c.width, c.height); this.hasInk = false; } },

            typedToImage() {
                const c = document.createElement('canvas'); c.width = 600; c.height = 200;
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#0f172a'; ctx.textBaseline = 'middle';
                ctx.font = '72px ' + this.fonts[this.fontIndex];
                ctx.fillText(this.typed.trim(), 20, 110);
                return c.toDataURL('image/png');
            },

            insert() {
                if (!this.canInsert()) return;
                this.signature = this.modalMode === 'draw' ? this.$refs.pad.toDataURL('image/png') : this.typedToImage();
                this.submitErr = '';
                this.closeModal();
            },

            // Use a saved signature template directly.
            useSaved(image) {
                if (!image) return;
                this.signature = image;
                this.submitErr = '';
                this.closeModal();
            },
        };
    }
</script>
@endsection
