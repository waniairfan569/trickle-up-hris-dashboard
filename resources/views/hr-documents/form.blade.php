@extends('layouts.hr-app')

@section('title', $template->name)
@section('breadcrumb', 'Documents')

@php
    $isEdit  = (bool) $document;
    $config  = [
        'schema' => $template->schema,
        'values' => empty($prefill) ? (object) [] : $prefill,
    ];
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="hrDocForm(@js($config))">

    <div class="flex items-center gap-3">
        <a href="{{ route('hr-documents.index') }}" class="rounded-lg p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $template->name }}</h1>
            @if($template->subtitle)<p class="text-sm text-slate-500 dark:text-slate-400">{{ $template->subtitle }}</p>@endif
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Who / period picker (new documents only — drives attendance prefill via reload) --}}
    @unless($isEdit)
        <form method="GET" action="{{ route('hr-documents.create') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <input type="hidden" name="template" value="{{ $template->id }}">
            <div class="grid gap-4 sm:grid-cols-[1fr_auto_auto] items-end">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Employee</label>
                    <select name="employee" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600">
                        <option value="">— Select employee —</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp['id'] }}" @selected($employee && $employee->id == $emp['id'])>{{ $emp['name'] }} · {{ $emp['department'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Month (for prefill)</label>
                    <input type="month" name="month" value="{{ $month }}" class="rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600">
                </div>
                <button class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 transition dark:bg-white dark:text-slate-900">
                    <i data-lucide="wand-2" class="h-4 w-4"></i> Load &amp; prefill
                </button>
            </div>
            @if($employee)
                <p class="text-xs text-emerald-600 mt-3 flex items-center gap-1.5"><i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i> Prefilled from {{ $employee->full_name }}’s attendance for {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}. Review &amp; edit below.</p>
            @endif
        </form>
    @endunless

    @if($isEdit || $employee)
        <form method="POST" action="{{ $isEdit ? route('hr-documents.update', $document) : route('hr-documents.store') }}">
            @csrf
            @if($isEdit) @method('PUT') @endif
            @unless($isEdit)
                <input type="hidden" name="template_id" value="{{ $template->id }}">
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="hidden" name="month" value="{{ $month }}">
            @endunless
            <input type="hidden" name="data" :value="JSON.stringify(values)">

            <div class="space-y-5">
                @foreach($template->schema as $section)
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
                        <div class="bg-slate-900 px-5 py-3 dark:bg-slate-900/80">
                            <h3 class="text-sm font-bold text-white tracking-wide">{{ $section['title'] }}</h3>
                        </div>
                        <div class="p-5 grid grid-cols-2 gap-x-5 gap-y-4">
                            @foreach($section['fields'] as $field)
                                @include('hr-documents._field', ['field' => $field])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="submit" name="action" value="save" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="save" class="h-4 w-4"></i> Save draft
                </button>
                <button type="submit" name="action" value="complete" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition">
                    <i data-lucide="check" class="h-4 w-4"></i> Save &amp; complete
                </button>
            </div>
        </form>
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-600">
            Select an employee above to start the document.
        </div>
    @endif
</div>

<script>
    function hrDocForm(cfg) {
        return {
            schema: cfg.schema,
            values: cfg.values || {},

            init() {
                (this.schema || []).forEach(sec => (sec.fields || []).forEach(f => {
                    const v = this.values[f.id];
                    if (f.type === 'checkbox') { if (!Array.isArray(v)) this.values[f.id] = []; }
                    else if (f.type === 'table') { if (!Array.isArray(v)) this.values[f.id] = []; }
                    else if (f.type === 'signature') { if (!v || typeof v !== 'object') this.values[f.id] = { image: '', name: '', date: '', typed: '' }; }
                    else if (f.type !== 'note') { if (v === undefined || v === null) this.values[f.id] = ''; }
                }));
            },

            toggle(id, opt) {
                let arr = Array.isArray(this.values[id]) ? this.values[id] : [];
                this.values[id] = arr.includes(opt) ? arr.filter(x => x !== opt) : [...arr, opt];
            },
            has(id, opt) { return Array.isArray(this.values[id]) && this.values[id].includes(opt); },

            addRow(id, cols) {
                let row = {}; cols.forEach(c => row[c] = '');
                this.values[id] = [...(this.values[id] || []), row];
            },
            removeRow(id, i) { this.values[id] = (this.values[id] || []).filter((_, idx) => idx !== i); },

            // ── signature pad ──
            initPad(canvas, id) {
                const ctx = canvas.getContext('2d');
                ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
                let drawing = false, last = null;
                const pos = (e) => {
                    const r = canvas.getBoundingClientRect(), t = e.touches ? e.touches[0] : e;
                    return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
                };
                const start = (e) => { drawing = true; last = pos(e); e.preventDefault(); };
                const move = (e) => { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; e.preventDefault(); };
                const end = () => { if (drawing) { drawing = false; this.values[id].image = canvas.toDataURL('image/png'); } };
                canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
                canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); canvas.addEventListener('touchend', end);
                if (this.values[id] && this.values[id].image) { const img = new Image(); img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height); img.src = this.values[id].image; }
            },
            clearPad(canvas, id) {
                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                this.values[id].image = ''; this.values[id].typed = '';
            },
            typeSign(canvas, id) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const name = this.values[id].typed || '';
                ctx.fillStyle = '#0f172a'; ctx.textBaseline = 'middle';
                ctx.font = '40px "Segoe Script", "Brush Script MT", "Comic Sans MS", cursive';
                ctx.fillText(name, 18, canvas.height / 2);
                this.values[id].image = name ? canvas.toDataURL('image/png') : '';
            },
        };
    }
</script>
@endsection
