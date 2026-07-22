@extends('layouts.hr-app')

@section('title', 'Preview · ' . $documentTemplate->name)
@section('breadcrumb', 'Document Templates')

@section('content')
<style>[x-cloak]{display:none!important} .sigpad{touch-action:none}</style>

@if(!$isPdf)
    <div class="max-w-2xl mx-auto space-y-6">
        <a href="{{ route('document-templates.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to templates
        </a>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-8 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-500 mb-4 mx-auto dark:bg-amber-500/10"><i data-lucide="file-text" class="h-7 w-7"></i></div>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ $documentTemplate->name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-md mx-auto">
                This template is a <span class="font-semibold">{{ strtoupper(pathinfo($documentTemplate->file_name, PATHINFO_EXTENSION)) }}</span> file. In-browser preview, field placement and e-signing need a <span class="font-semibold">PDF</span>. Re-upload a PDF version to use the full workflow, or download the current file.
            </p>
            <div class="flex items-center justify-center gap-2 mt-5">
                <a href="{{ route('document-templates.preview', $documentTemplate) }}" class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                    <i data-lucide="download" class="h-4 w-4"></i> Download file
                </a>
                <a href="{{ route('document-templates.edit', $documentTemplate) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                    <i data-lucide="upload" class="h-4 w-4"></i> Edit &amp; upload PDF
                </a>
            </div>
        </div>
    </div>
@else

@php
    $fieldList = $fields->values()->map(fn ($f, $i) => array_merge($f, ['idx' => $i]))->all();
@endphp
<script>
    window.__pvFileUrl = "{{ route('document-templates.preview', $documentTemplate) }}";
    window.__pvFields = @json($fieldList);
    window.__pvSigners = @json($signers);
</script>
<script src="https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
<script>if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';</script>

<div x-data="previewer()" x-init="init()" class="space-y-4">
    @include('company-documents.partials.builder-steps', ['template' => $documentTemplate, 'current' => 3])

    <!-- Top bar -->
    <div class="flex items-center justify-between">
        <a href="{{ route('document-templates.edit', $documentTemplate) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to field setup
        </a>
        <a href="{{ route('document-templates.send-form', $documentTemplate) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
            <i data-lucide="send" class="h-4 w-4"></i> Send for signature
        </a>
    </div>

    <!-- Viewer -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700 flex flex-col" style="height: calc(100vh - 180px); min-height: 560px;">
        <!-- Header: zoom -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 dark:border-slate-700/60">
            <h1 class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $documentTemplate->name }}</h1>
            <div class="flex items-center gap-2 text-slate-500">
                <button type="button" @click="zoomOut()" class="h-7 w-7 inline-flex items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="minus-circle" class="h-4 w-4"></i></button>
                <span class="text-xs font-semibold min-w-[92px] text-center" x-text="zoom === 1 ? 'Zoom: Fit Width' : 'Zoom: ' + Math.round(zoom * 100) + '%'"></span>
                <button type="button" @click="zoomIn()" class="h-7 w-7 inline-flex items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="plus-circle" class="h-4 w-4"></i></button>
            </div>
        </div>

        <div class="flex flex-1 min-h-0">
            <!-- Signers sidebar -->
            <aside class="w-56 shrink-0 border-r border-slate-100 dark:border-slate-700/60 p-3 overflow-y-auto hidden sm:block">
                <p class="px-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Signers</p>
                <button type="button" @click="activeSigner = 'all'"
                        class="w-full flex items-center gap-3 rounded-xl px-3 py-2 mb-1 text-left"
                        :class="activeSigner === 'all' ? 'bg-slate-100 dark:bg-slate-700' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 text-[11px] font-bold text-slate-500" x-text="signers.length"></span>
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">All Signers</span>
                </button>
                <template x-for="(s, i) in signers" :key="i">
                    <button type="button" @click="activeSigner = i"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2 mb-1 text-left"
                            :class="activeSigner === i ? 'bg-slate-100 dark:bg-slate-700' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold text-white" :class="signerColor(s.role)" x-text="initials(s.name)"></span>
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate" x-text="s.name"></span>
                    </button>
                </template>
            </aside>

            <!-- Document -->
            <div class="flex-1 min-w-0 overflow-auto bg-slate-50 dark:bg-slate-900/40 p-6" x-ref="pdfWrap">
                <div x-show="pdfLoading" class="py-20 text-center text-sm text-slate-400"><i data-lucide="loader" class="h-5 w-5 mx-auto mb-2 animate-spin"></i> Loading document…</div>
                <div x-show="!pdfReady && !pdfLoading" class="py-20 text-center text-sm text-slate-500" x-text="pdfError"></div>
                <div x-show="pdfReady" class="space-y-4 w-fit mx-auto">
                    <template x-for="pg in pages" :key="'pg' + pg.num">
                        <div class="relative shadow border border-slate-200 bg-white dark:border-slate-700" :style="`width:${pg.width}px;height:${pg.height}px`" :data-page="pg.num">
                            <canvas :id="'pvcanvas-' + pg.num" class="block"></canvas>
                            <template x-for="f in fieldsOnPage(pg.num)" :key="'f' + f.idx">
                                <div class="absolute" :style="boxStyle(f, pg)" x-show="highlighted(f)" x-cloak>
                                    <!-- signature / initials box -->
                                    <template x-if="f.type === 'signature' || f.type === 'initials'">
                                        <button type="button" @click="openSign(f)"
                                                class="w-full h-full rounded border-2 flex items-center justify-center text-[10px] font-bold uppercase tracking-wide relative bg-white/70 hover:bg-brand-50"
                                                :class="signatures[f.idx] ? 'border-emerald-400' : 'border-brand-500 border-dashed text-brand-700'">
                                            <template x-if="signatures[f.idx]">
                                                <img :src="signatures[f.idx]" class="max-h-full max-w-full object-contain p-0.5">
                                            </template>
                                            <template x-if="!signatures[f.idx]">
                                                <span>Click to sign <span class="text-rose-500">*</span></span>
                                            </template>
                                        </button>
                                    </template>
                                    <!-- text auto-fill field -->
                                    <template x-if="f.type === 'text'">
                                        <div class="w-full h-full rounded border border-dashed border-slate-300 bg-slate-50/70 flex items-center px-1 text-[10px] font-semibold text-slate-500 truncate" x-text="f.label"></div>
                                    </template>
                                    <!-- saved signature (stamped automatically) -->
                                    <template x-if="f.type === 'saved_signature'">
                                        <div class="w-full h-full rounded border-2 border-emerald-300 bg-white/80 flex items-center justify-center p-0.5">
                                            <img :src="f.image" class="max-h-full max-w-full object-contain" alt="">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Signature modal -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeModal()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Add your signature</h2>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>

            <!-- tabs -->
            <div class="flex gap-1 px-6 pt-3 border-b border-slate-100 dark:border-slate-700/60">
                <button type="button" @click="modalMode = 'draw'" class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 -mb-px" :class="modalMode === 'draw' ? 'border-slate-800 text-slate-800 dark:border-white dark:text-white' : 'border-transparent text-slate-400'">
                    <i data-lucide="pen-tool" class="h-4 w-4"></i>
                </button>
                <button type="button" @click="modalMode = 'type'" class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 -mb-px" :class="modalMode === 'type' ? 'border-slate-800 text-slate-800 dark:border-white dark:text-white' : 'border-transparent text-slate-400'">
                    <i data-lucide="keyboard" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="p-6">
                <!-- draw -->
                <div x-show="modalMode === 'draw'" class="rounded-xl border border-slate-200 dark:border-slate-600 relative">
                    <canvas x-ref="pad" width="600" height="200" class="sigpad w-full bg-white rounded-xl"></canvas>
                    <button type="button" @click="clearPad()" class="absolute bottom-2 left-2 text-slate-400 hover:text-rose-500"><i data-lucide="x" class="h-4 w-4"></i></button>
                    <div class="absolute bottom-3 left-10 right-6 border-b border-slate-300 pointer-events-none"></div>
                </div>

                <!-- type -->
                <div x-show="modalMode === 'type'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-600 relative">
                    <div class="h-[200px] flex items-center px-6">
                        <input type="text" x-model="typed" placeholder="Type your name"
                               class="w-full bg-transparent border-0 focus:ring-0 text-4xl text-slate-900 dark:text-white outline-none"
                               :style="`font-family:${fonts[fontIndex]};`">
                    </div>
                    <button type="button" @click="typed = ''" class="absolute bottom-2 left-2 text-slate-400 hover:text-rose-500"><i data-lucide="x" class="h-4 w-4"></i></button>
                    <div class="absolute bottom-3 left-10 right-40 border-b border-slate-300 pointer-events-none"></div>
                    <button type="button" @click="fontIndex = (fontIndex + 1) % fonts.length" class="absolute bottom-2 right-3 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800">
                        <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Change font
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                <span class="text-xs text-slate-500 dark:text-slate-400">I understand this is a legal representation of my signature.</span>
                <button type="button" @click="insert()" :disabled="!canInsert()"
                        :class="canInsert() ? 'bg-brand-600 text-slate-900 hover:bg-brand-700' : 'bg-slate-200 text-slate-400 cursor-not-allowed dark:bg-slate-700'"
                        class="inline-flex items-center gap-1.5 rounded-xl px-6 py-2.5 text-sm font-bold shadow-sm">
                    Insert
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function previewer() {
        let __pdfPages = [];
        return {
            fields: window.__pvFields || [],
            signers: window.__pvSigners || [],
            activeSigner: 'all',
            pages: [],
            pdfReady: false,
            pdfLoading: false,
            pdfError: '',
            zoom: 1,
            baseW: 700,
            signatures: {},
            // modal
            modalOpen: false,
            activeField: null,
            modalMode: 'draw',
            typed: '',
            fontIndex: 0,
            hasInk: false,
            fonts: ['"Brush Script MT", "Segoe Script", cursive', '"Lucida Handwriting", "Segoe Script", cursive', '"Comic Sans MS", cursive', 'Georgia, serif'],

            init() {
                this.baseW = Math.min(760, (this.$refs.pdfWrap && this.$refs.pdfWrap.clientWidth - 48) || 700);
                this.loadPdf();
            },

            party(v) {
                return ({ employee: 'employee', line_manager: 'line_manager', hr_admin: 'sender_party', me_now: 'sender_party', sender: 'sender_party' })[v] || v;
            },
            highlighted(f) {
                if (this.activeSigner === 'all') return true;
                const s = this.signers[this.activeSigner];
                return s && this.party(f.assignee) === this.party(s.role);
            },
            signerColor(role) {
                return ({ employee: 'bg-teal-500', line_manager: 'bg-violet-500', hr_admin: 'bg-orange-500', me_now: 'bg-orange-500', sender: 'bg-orange-500', specific: 'bg-sky-500' })[role] || 'bg-slate-400';
            },
            initials(name) {
                return (name || '').split(/[\s,]+/).filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase() || '?';
            },

            fieldsOnPage(n) { return this.fields.filter(f => f.page === n); },
            boxStyle(f, pg) {
                return `left:${f.x * pg.width}px; top:${f.y * pg.height}px; width:${f.w * pg.width}px; height:${(f.h || 0.04) * pg.height}px;`;
            },

            async loadPdf() {
                this.pdfLoading = true; this.pdfError = ''; this.pages = []; this.pdfReady = false; __pdfPages = [];
                try {
                    const lib = window.pdfjsLib;
                    if (!lib) { this.pdfError = 'PDF viewer failed to load.'; this.pdfLoading = false; return; }
                    const doc = await lib.getDocument(window.__pvFileUrl).promise;
                    const targetW = this.baseW * this.zoom;
                    const meta = [];
                    for (let n = 1; n <= doc.numPages; n++) {
                        const page = await doc.getPage(n);
                        const vp = page.getViewport({ scale: targetW / page.getViewport({ scale: 1 }).width });
                        __pdfPages.push({ page, vp });
                        meta.push({ num: n, width: Math.round(vp.width), height: Math.round(vp.height) });
                    }
                    this.pages = meta; this.pdfReady = true; this.pdfLoading = false;
                    await this.$nextTick();
                    for (let i = 0; i < meta.length; i++) {
                        const c = document.getElementById('pvcanvas-' + meta[i].num);
                        if (!c) continue;
                        c.width = meta[i].width; c.height = meta[i].height;
                        await __pdfPages[i].page.render({ canvasContext: c.getContext('2d'), viewport: __pdfPages[i].vp }).promise;
                    }
                    if (window.lucide) lucide.createIcons();
                } catch (e) {
                    this.pdfReady = false; this.pdfLoading = false;
                    this.pdfError = 'Could not render the document (' + (e && e.message ? e.message : e) + ').';
                }
            },

            zoomIn() { this.zoom = Math.min(2, this.zoom + 0.25); this.loadPdf(); },
            zoomOut() { this.zoom = Math.max(0.5, this.zoom - 0.25); this.loadPdf(); },

            // ---- signature modal ----
            openSign(f) {
                this.activeField = f;
                this.modalMode = 'draw'; this.typed = ''; this.hasInk = false;
                this.modalOpen = true;
                this.$nextTick(() => { this.setupPad(); if (window.lucide) lucide.createIcons(); });
            },
            closeModal() { this.modalOpen = false; this.activeField = null; },
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
                if (!this.canInsert() || !this.activeField) return;
                const data = this.modalMode === 'draw' ? this.$refs.pad.toDataURL('image/png') : this.typedToImage();
                this.signatures[this.activeField.idx] = data;
                this.closeModal();
            },
        };
    }
</script>
@endif
@endsection
