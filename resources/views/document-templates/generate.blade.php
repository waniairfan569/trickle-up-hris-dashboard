@extends('layouts.hr-app')

@section('title', 'Generate document')
@section('breadcrumb', 'Document Templates')

@section('content')
<style>[x-cloak]{display:none!important}</style>

@php
    $employeeOpts = $employees->map(fn ($e) => [
        'id' => (string) $e->id,
        'label' => trim(($e->last_name ? $e->last_name . ', ' : '') . $e->first_name) . ($e->job_title ? ' — ' . $e->job_title : ''),
    ])->values();
@endphp
<script>
    window.__genEmployees = @json($employeeOpts);
    window.__genFillUrl = "{{ route('document-templates.fill-data', $documentTemplate) }}";
    window.__genTplName = @json($documentTemplate->name);
</script>

<!-- pdf-lib (CDN, pure JS) — stamps profile data onto the PDF in the browser -->
<script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>

<div class="max-w-2xl mx-auto space-y-6" x-data="generator()">
    <div class="flex items-center gap-3">
        <a href="{{ route('document-templates.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700" title="Back">
            <i data-lucide="arrow-left" class="h-5 w-5"></i>
        </a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Generate document</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $documentTemplate->name }} · auto-fills placed fields from the employee's profile</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-5">
        <!-- Employee picker -->
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Employee</label>
            <div x-data="{ open: false, q: '' }" @click.outside="open = false" class="relative">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-left dark:border-slate-600 dark:bg-slate-900">
                    <span :class="employeeId ? 'text-slate-700 dark:text-white' : 'text-slate-400'" x-text="employeeLabel()"></span>
                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
                </button>
                <div x-show="open" x-cloak x-transition.origin.top
                     class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                    <input x-model="q" type="text" placeholder="Search employees…"
                           class="w-full rounded-lg border-slate-300 text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <div class="max-h-60 overflow-y-auto mt-2 space-y-0.5">
                        <template x-for="e in employees.filter(o => o.label.toLowerCase().includes(q.toLowerCase()))" :key="e.id">
                            <button type="button" @click="employeeId = e.id; open = false; done = false"
                                    class="w-full text-left px-2 py-1.5 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                    :class="employeeId === e.id ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-500/10' : 'text-slate-700 dark:text-slate-200'">
                                <span x-text="e.label"></span>
                            </button>
                        </template>
                        <p x-show="!employees.length" class="text-xs text-slate-400 px-2 py-1">No eligible employees for this template's scope.</p>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="error" x-cloak class="rounded-xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20" x-text="error"></div>
        <div x-show="done" x-cloak class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <i data-lucide="check-circle" class="h-4 w-4"></i> Filled PDF downloaded.
        </div>

        <button type="button" @click="generate()" :disabled="!employeeId || generating"
                :class="(!employeeId || generating) && 'opacity-50 cursor-not-allowed'"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
            <template x-if="!generating"><i data-lucide="file-down" class="h-4 w-4"></i></template>
            <template x-if="generating"><i data-lucide="loader" class="h-4 w-4 animate-spin"></i></template>
            <span x-text="generating ? 'Generating…' : 'Generate filled PDF'"></span>
        </button>

        <p class="text-[11px] text-slate-400">Profile fields you placed on the document (Step 4) are stamped at their positions. Signature/initials areas are outlined for the signing step.</p>
    </div>
</div>

<script>
    function generator() {
        return {
            employees: window.__genEmployees || [],
            employeeId: '',
            generating: false,
            error: '',
            done: false,

            employeeLabel() {
                const e = this.employees.find(o => o.id === this.employeeId);
                return e ? e.label : 'Select an employee…';
            },

            async generate() {
                if (!this.employeeId || this.generating) return;
                this.generating = true; this.error = ''; this.done = false;
                try {
                    if (!window.PDFLib) throw new Error('PDF engine failed to load. Check your connection.');
                    const res = await fetch(window.__genFillUrl + '?employee=' + encodeURIComponent(this.employeeId), {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('Could not load field data (' + res.status + ').');
                    const data = await res.json();

                    const bytes = await fetch(data.fileUrl).then(r => r.arrayBuffer());
                    const { PDFDocument, StandardFonts, rgb } = window.PDFLib;
                    const pdf = await PDFDocument.load(bytes);
                    const font = await pdf.embedFont(StandardFonts.Helvetica);
                    const pages = pdf.getPages();

                    for (const f of data.fields) {
                        const page = pages[f.page - 1];
                        if (!page) continue;
                        const pw = page.getWidth(), ph = page.getHeight();
                        const size = Math.max(8, Math.min(13, (f.h || 0.03) * ph * 0.7));

                        if (f.type === 'signature' || f.type === 'initials') {
                            // Outline the area for the signing step.
                            const boxH = Math.max(size + 6, (f.h || 0.035) * ph);
                            page.drawRectangle({
                                x: f.x * pw, y: ph - f.y * ph - boxH, width: f.w * pw, height: boxH,
                                borderColor: rgb(0.6, 0.6, 0.6), borderWidth: 0.7, borderDashArray: [2, 2],
                            });
                            page.drawText(f.label, { x: f.x * pw + 2, y: ph - f.y * ph - size, size: Math.min(size, 8), font, color: rgb(0.6, 0.6, 0.6) });
                            continue;
                        }

                        if (!f.value) continue;
                        page.drawText(String(f.value), {
                            x: f.x * pw + 2, y: ph - f.y * ph - size, size, font, color: rgb(0.1, 0.1, 0.12),
                        });
                    }

                    const outBytes = await pdf.save();
                    const blob = new Blob([outBytes], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const safe = (data.fileName || 'document') + ' — ' + (data.employee || '');
                    const a = document.createElement('a');
                    a.href = url; a.download = safe.trim().replace(/[^\w.\- ]+/g, '') + '.pdf';
                    document.body.appendChild(a); a.click(); a.remove();
                    URL.revokeObjectURL(url);
                    this.done = true;
                } catch (e) {
                    this.error = (e && e.message) ? e.message : 'Generation failed.';
                } finally {
                    this.generating = false;
                }
            },
        };
    }
</script>
@endsection
