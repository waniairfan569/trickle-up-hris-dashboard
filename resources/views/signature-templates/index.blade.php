@extends('layouts.hr-app')

@section('title', 'Signature Templates')
@section('breadcrumb', 'Signature Templates')

@section('content')
<style>[x-cloak]{display:none!important} .sigpad{touch-action:none}</style>
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="signature" class="h-6 w-6 text-brand-500"></i> Signature Templates
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Save reusable signatures once, then drag them onto documents in the builder — they’re stamped automatically, no drawing each time.</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Create --}}
    <form method="POST" action="{{ route('signature-templates.store') }}" x-data="sigPad()" @submit="return prepare($event)"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
        @csrf
        <input type="hidden" name="image_data" x-ref="imageData">

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Signature name <span class="text-rose-500">*</span></label>
            <input type="text" name="name" required maxlength="100" placeholder="e.g. CEO signature, HR — Sobia"
                   class="w-full sm:w-80 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>

        {{-- Mode tabs --}}
        <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
            @foreach(['draw' => 'Draw', 'type' => 'Type', 'upload' => 'Upload'] as $m => $lbl)
                <button type="button" @click="mode = '{{ $m }}'" :class="mode === '{{ $m }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2 text-sm font-bold transition">{{ $lbl }}</button>
            @endforeach
        </div>

        {{-- Draw --}}
        <div x-show="mode === 'draw'" class="space-y-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50 dark:bg-slate-900/40 dark:border-slate-700">
                <canvas x-ref="pad" class="sigpad w-full rounded-xl" style="height:180px"></canvas>
            </div>
            <button type="button" @click="clearPad()" class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400">Clear</button>
        </div>

        {{-- Type --}}
        <div x-show="mode === 'type'" x-cloak class="space-y-2">
            <input type="text" x-model="typed" maxlength="40" placeholder="Type a name"
                   class="w-full sm:w-80 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            <div class="flex flex-wrap gap-2">
                <template x-for="f in fonts" :key="f">
                    <button type="button" @click="font = f" :style="`font-family:${f}`" :class="font === f ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'" class="rounded-xl border px-4 py-2 text-lg" x-text="typed || 'Signature'"></button>
                </template>
            </div>
        </div>

        {{-- Upload --}}
        <div x-show="mode === 'upload'" x-cloak class="space-y-2">
            <input type="file" accept="image/png,image/jpeg" @change="onUpload($event)" class="block text-sm text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
            <template x-if="uploaded"><img :src="uploaded" alt="signature" class="max-h-24 rounded-lg border border-slate-200 dark:border-slate-700 bg-white p-2"></template>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="save" class="h-4 w-4"></i> Save signature</button>
        </div>
    </form>

    {{-- Saved --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Saved signatures ({{ $templates->count() }})</h2></div>
        @if($templates->count())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5">
                @foreach($templates as $t)
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                        <div class="flex items-center justify-center h-24 rounded-lg bg-slate-50 dark:bg-slate-900/40 mb-2">
                            <img src="{{ $t->image_data }}" alt="{{ $t->name }}" class="max-h-20 max-w-full object-contain">
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $t->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ optional($t->creator)->full_name ?? '—' }} · {{ $t->created_at->format('d M Y') }}</p>
                            </div>
                            <form method="POST" action="{{ route('signature-templates.destroy', $t) }}" onsubmit="return confirm('Delete this signature?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-slate-700"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-14 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="signature" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No saved signatures yet</p>
                <p class="text-xs text-slate-400 mt-1">Create one above — draw, type or upload.</p>
            </div>
        @endif
    </div>
</div>

<script>
    function sigPad() {
        return {
            mode: 'draw', typed: '', font: "'Dancing Script', cursive", uploaded: null,
            fonts: ["'Dancing Script', cursive", "'Great Vibes', cursive", "Georgia, serif", "'Brush Script MT', cursive"],
            ctx: null, drawing: false, hasDrawn: false,
            init() { this.$nextTick(() => this.setupPad()); },
            setupPad() {
                const c = this.$refs.pad; if (!c) return;
                const rect = c.getBoundingClientRect();
                c.width = rect.width; c.height = 180;
                const ctx = c.getContext('2d');
                ctx.lineWidth = 2.4; ctx.lineJoin = 'round'; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
                this.ctx = ctx;
                const pos = e => { const r = c.getBoundingClientRect(); const t = e.touches ? e.touches[0] : e; return { x: t.clientX - r.left, y: t.clientY - r.top }; };
                const start = e => { e.preventDefault(); this.drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); };
                const move = e => { if (!this.drawing) return; e.preventDefault(); const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); this.hasDrawn = true; };
                const end = () => { this.drawing = false; };
                c.addEventListener('pointerdown', start); c.addEventListener('pointermove', move);
                window.addEventListener('pointerup', end);
            },
            clearPad() { if (this.ctx) { this.ctx.clearRect(0, 0, this.$refs.pad.width, this.$refs.pad.height); this.hasDrawn = false; } },
            onUpload(e) {
                const f = e.target.files[0]; if (!f) return;
                const r = new FileReader(); r.onload = () => { this.uploaded = r.result; }; r.readAsDataURL(f);
            },
            typedToImage() {
                const c = document.createElement('canvas'); c.width = 520; c.height = 180;
                const ctx = c.getContext('2d'); ctx.fillStyle = '#0f172a'; ctx.textBaseline = 'middle'; ctx.textAlign = 'center';
                ctx.font = `54px ${this.font}`;
                ctx.fillText(this.typed || '', c.width / 2, c.height / 2);
                return c.toDataURL('image/png');
            },
            prepare(e) {
                let data = null;
                if (this.mode === 'draw') { if (!this.hasDrawn) { e.preventDefault(); alert('Please draw your signature.'); return false; } data = this.$refs.pad.toDataURL('image/png'); }
                else if (this.mode === 'type') { if (!this.typed.trim()) { e.preventDefault(); alert('Please type a name.'); return false; } data = this.typedToImage(); }
                else { if (!this.uploaded) { e.preventDefault(); alert('Please choose an image.'); return false; } data = this.uploaded; }
                this.$refs.imageData.value = data;
                return true;
            },
        };
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&family=Great+Vibes&display=swap" rel="stylesheet">
@endsection
