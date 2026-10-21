@extends('layouts.hr-app')

@section('title', 'Sign — ' . $document->template_name)
@section('breadcrumb', 'To sign')

@php
    $data          = $document->data ?? [];
    $allFields     = collect($document->schema)->flatMap(fn ($s) => $s['fields'] ?? [])->keyBy('id');
    $myFields      = collect($signer->field_ids)->map(fn ($fid) => ['id' => $fid, 'label' => $allFields[$fid]['label'] ?? 'Signature'])->values()->all();
    $alreadySigned = (bool) $signer->signed_at;
    $cfg           = ['fields' => $myFields, 'name' => auth()->user()->full_name, 'today' => now()->toDateString()];
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="hrSignPad(@js($cfg))">

    <div class="flex items-center gap-3">
        <a href="{{ route('hr-documents.to-sign') }}" class="rounded-lg p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $document->template_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Please review the document below and add your signature.</p>
        </div>
    </div>

    @if(session('error'))
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
    @endif

    @if($alreadySigned)
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 flex items-center gap-2">
            <i data-lucide="check-circle-2" class="h-4 w-4"></i> You signed this on {{ $signer->signed_at->format('d M Y, H:i') }}.
        </div>
    @elseif(!empty($waitingOn))
        {{-- This document signs in order and someone is ahead of them. Say so
             rather than showing a button that would be refused. --}}
        <div class="rounded-xl bg-amber-50 p-4 border border-amber-200 text-sm text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 flex items-start gap-2">
            <i data-lucide="hourglass" class="h-4 w-4 mt-0.5 shrink-0"></i>
            <span>
                <b>Waiting on {{ optional($waitingOn->user)->full_name ?? 'someone else' }}.</b>
                This document is signed in order — you'll be asked once they have signed.
            </span>
        </div>
    @endif

    @include('hr-documents._readonly', ['document' => $document, 'data' => $data])

    @unless($alreadySigned || !empty($waitingOn))
        @php
            // Questions the document asks but nobody has answered. The employee
            // is the one being asked, so let them answer here rather than
            // signing a form with blank questions on it.
            $askable = ['text', 'textarea', 'number', 'date', 'radio', 'select', 'checkbox'];
            $toAnswer = collect($document->schema)
                ->flatMap(fn ($s) => $s['fields'] ?? [])
                ->filter(fn ($f) => is_array($f) && ! empty($f['id'])
                    && in_array($f['type'] ?? 'text', $askable, true)
                    && empty($f['prefill'])
                    && (is_array($data[$f['id']] ?? null)
                        ? empty($data[$f['id']])
                        : trim((string) ($data[$f['id']] ?? '')) === ''))
                ->values();
        @endphp

        <form method="POST" action="{{ route('hr-documents.sign.store', $document) }}">
            @csrf
            <input type="hidden" name="signatures" :value="JSON.stringify(values)">

            @if($toAnswer->isNotEmpty())
                <div class="mb-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700/60">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">Your answers</h3>
                        <p class="text-xs text-slate-400 mt-0.5">These questions are for you. They go onto the document you're signing.</p>
                    </div>
                    <div class="p-5 space-y-5">
                        @foreach($toAnswer as $f)
                            @php $fid = $f['id']; $ftype = $f['type'] ?? 'text'; @endphp
                            <div>
                                <label for="ans-{{ $fid }}" class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">{{ $f['label'] ?? $fid }}</label>

                                @if($ftype === 'textarea')
                                    <textarea id="ans-{{ $fid }}" name="answers[{{ $fid }}]" rows="3" maxlength="2000"
                                              class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('answers.' . $fid) }}</textarea>
                                @elseif($ftype === 'radio' || $ftype === 'select')
                                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                                        @foreach($f['options'] ?? [] as $opt)
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                                <input type="radio" name="answers[{{ $fid }}]" value="{{ $opt }}" @checked(old('answers.' . $fid) === $opt)
                                                       class="border-slate-300 text-brand-500 focus:ring-brand-500">
                                                {{ $opt }}
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($ftype === 'checkbox')
                                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                                        @foreach($f['options'] ?? [] as $opt)
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                                <input type="checkbox" name="answers[{{ $fid }}][]" value="{{ $opt }}"
                                                       class="rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                                                {{ $opt }}
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <input type="{{ $ftype === 'date' ? 'date' : ($ftype === 'number' ? 'number' : 'text') }}"
                                           id="ans-{{ $fid }}" name="answers[{{ $fid }}]" value="{{ old('answers.' . $fid) }}" maxlength="500"
                                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                @endif
                            </div>
                        @endforeach
                        <p class="text-[11px] text-slate-400">Anything you leave blank stays blank on the document.</p>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-2xl border-2 border-brand-200 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-brand-500/30">
                <div class="bg-brand-50 px-5 py-3 dark:bg-brand-500/10"><h3 class="text-sm font-bold text-brand-700 dark:text-brand-300 tracking-wide">Your signature</h3></div>
                <div class="p-5 space-y-5">
                    @foreach($myFields as $mf)
                        <div x-data="{}" x-init="$nextTick(() => initPad($refs.pad, '{{ $mf['id'] }}'))">
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">{{ $mf['label'] }}</label>
                            <canvas x-ref="pad" width="640" height="150" class="w-full h-[150px] bg-white border border-slate-300 rounded-lg dark:border-slate-600" style="touch-action:none;"></canvas>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <input type="text" x-model="values['{{ $mf['id'] }}'].typed" @input="typeSign($refs.pad, '{{ $mf['id'] }}')" placeholder="…or type to sign" class="flex-1 min-w-[160px] rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
                                <button type="button" @click="clearPad($refs.pad, '{{ $mf['id'] }}')" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700"><i data-lucide="eraser" class="h-3.5 w-3.5"></i> Clear</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <input type="text" x-model="values['{{ $mf['id'] }}'].name" placeholder="Name (printed)" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
                                <input type="date" x-model="values['{{ $mf['id'] }}'].date" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 flex items-center justify-end">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition">
                    <i data-lucide="check" class="h-4 w-4"></i> Sign &amp; submit
                </button>
            </div>
        </form>
    @endunless
</div>

<script>
    function hrSignPad(cfg) {
        return {
            values: {},
            init() {
                (cfg.fields || []).forEach(f => { this.values[f.id] = { image: '', typed: '', name: cfg.name || '', date: cfg.today || '' }; });
            },
            initPad(canvas, id) {
                const ctx = canvas.getContext('2d');
                ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
                let drawing = false, last = null;
                const pos = (e) => { const r = canvas.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) }; };
                const start = (e) => { drawing = true; last = pos(e); e.preventDefault(); };
                const move = (e) => { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; e.preventDefault(); };
                const end = () => { if (drawing) { drawing = false; this.values[id].image = canvas.toDataURL('image/png'); } };
                canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
                canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); canvas.addEventListener('touchend', end);
            },
            clearPad(canvas, id) { canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); this.values[id].image = ''; this.values[id].typed = ''; },
            typeSign(canvas, id) {
                const ctx = canvas.getContext('2d'); ctx.clearRect(0, 0, canvas.width, canvas.height);
                const name = this.values[id].typed || '';
                ctx.fillStyle = '#0f172a'; ctx.textBaseline = 'middle'; ctx.font = '42px "Segoe Script", "Brush Script MT", cursive';
                ctx.fillText(name, 18, canvas.height / 2);
                this.values[id].image = name ? canvas.toDataURL('image/png') : '';
            },
        };
    }
</script>
@endsection
