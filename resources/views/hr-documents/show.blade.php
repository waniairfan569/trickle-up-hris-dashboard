@extends('layouts.hr-app')

@section('title', $document->title ?? $document->template_name)
@section('breadcrumb', 'Documents')

@php
    $data = $document->data ?? [];
    $sigFields = collect($document->signatureFields());
    $hasManagerField = $sigFields->contains(fn ($f) => \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($f['label'] ?? ''), 'manager'));
    $defaultManagerId = optional($document->employee)->manager_id;
@endphp

@section('content')
{{-- A refused send redirects back; reopen the dialog so the error is read in
     context rather than behind it. --}}
<div class="max-w-4xl mx-auto space-y-6" x-data="{ sendOpen: {{ old('manager_mode') ? 'true' : 'false' }} }">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <a href="{{ route('hr-documents.index') }}" class="rounded-lg p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $document->template_name }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ optional($document->employee)->full_name ?? '—' }}
                    @if($document->period_start) · {{ $document->period_start->format('F Y') }} @endif
                    ·
                    @if($document->status === 'completed')
                        <span class="text-emerald-600 font-semibold">Completed</span>
                    @elseif($document->status === 'sent')
                        <span class="text-sky-600 font-semibold">Awaiting signature</span>@if($document->sent_at) · sent {{ $document->sent_at->format('d M Y') }}@endif
                    @else
                        <span class="text-amber-600 font-semibold">Draft</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('hr-documents.edit', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="pencil" class="h-4 w-4"></i> Edit</a>
            <a href="{{ route('hr-documents.pdf', [$document, 'preview' => 1]) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-4 w-4"></i> Preview</a>
            <a href="{{ route('hr-documents.docx', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="file-text" class="h-4 w-4"></i> Word</a>
            <a href="{{ route('hr-documents.pdf', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="download" class="h-4 w-4"></i> PDF</a>
            <button type="button" @click="sendOpen = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition"><i data-lucide="send" class="h-4 w-4"></i> {{ $document->signers->isNotEmpty() ? 'Resend' : 'Send for signature' }}</button>
            <form method="POST" action="{{ route('hr-documents.destroy', $document) }}" onsubmit="return confirm('Delete this document permanently?')">
                @csrf @method('DELETE')
                <button class="inline-flex items-center rounded-xl border border-slate-200 p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition dark:border-slate-600 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
    @endif

    {{-- Signer status (once sent) --}}
    @if($document->signers->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Signature status</div>
            <div class="space-y-2">
                @foreach($document->signers as $signer)
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ optional($signer->user)->full_name ?? 'User #'.$signer->user_id }} <span class="text-xs font-normal text-slate-400">· {{ ucfirst($signer->role ?? 'signer') }}</span></span>
                        @if($signer->signed_at)
                            <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold"><i data-lucide="check-circle-2" class="h-4 w-4"></i> Signed {{ $signer->signed_at->format('d M Y, H:i') }}</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-amber-600 font-semibold"><i data-lucide="clock" class="h-4 w-4"></i> Awaiting signature</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @include('hr-documents._readonly', ['document' => $document, 'data' => $data])

    {{-- Send-for-signature dialog --}}
    <div x-show="sendOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-slate-900/50" @click="sendOpen = false"></div>
        {{-- Capped to the window and scrolled inside, so the signature pad can't
             push the buttons off a short screen. --}}
        <div class="relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800" @click.stop>
            <form method="POST" action="{{ route('hr-documents.send', $document) }}" class="flex min-h-0 flex-col">
                @csrf
                <div class="shrink-0 p-5 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2"><i data-lucide="send" class="h-5 w-5 text-brand-500"></i> Send for signature</h3>
                    <p class="text-xs text-slate-500 mt-1">Choose who needs to sign this document.</p>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-5 space-y-4">
                    {{-- Employee (always) --}}
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <div class="h-9 w-9 shrink-0 rounded-lg bg-brand-50 text-brand-600 grid place-items-center dark:bg-brand-500/10"><i data-lucide="user" class="h-4 w-4"></i></div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ optional($document->employee)->full_name ?? '—' }}</div>
                            <div class="text-xs text-slate-500">Employee — signs the Employee section</div>
                        </div>
                    </div>

                    @if($hasManagerField)
                        {{-- The manager section: signed here before sending, sent to the
                             line manager, or left blank. --}}
                        <div x-data="adminSignPad()" class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Manager section</p>

                            <label class="flex cursor-pointer select-none items-start gap-3">
                                <input type="radio" name="manager_mode" value="self" x-model="mode" class="mt-0.5 border-slate-300 text-brand-500 focus:ring-brand-500">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    I'll sign it now
                                    <span class="block text-xs font-normal text-slate-500">The employee receives it already countersigned.</span>
                                </span>
                            </label>

                            <div x-show="mode === 'self'" x-cloak class="pl-7 space-y-2">
                                <canvas x-ref="pad" width="640" height="140"
                                        class="h-[140px] w-full rounded-lg border border-slate-300 bg-white dark:border-slate-600"
                                        style="touch-action:none;"></canvas>
                                <input type="hidden" name="admin_signature" :value="image">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="text" x-model="typed" @input="renderTyped()" placeholder="…or type your name"
                                           class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                    <button type="button" @click="clearPad()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Clear</button>
                                </div>
                                <p class="text-[11px]" :class="image ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                                   x-text="image ? 'Signature captured' : 'Draw above or type your name.'"></p>
                            </div>

                            <label class="flex cursor-pointer select-none items-start gap-3">
                                <input type="radio" name="manager_mode" value="manager" x-model="mode" class="mt-0.5 border-slate-300 text-brand-500 focus:ring-brand-500">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Send it to the line manager to sign
                                </span>
                            </label>

                            <div x-show="mode === 'manager'" x-cloak class="pl-7">
                                <select name="manager_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
                                    <option value="">— Select manager —</option>
                                    @foreach($employees as $emp)
                                        @if(!$document->employee || $emp['id'] != $document->employee->id)
                                            <option value="{{ $emp['id'] }}" @selected($defaultManagerId == $emp['id'])>{{ $emp['name'] }} · {{ $emp['department'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @unless($defaultManagerId)
                                    <p class="mt-1.5 text-xs text-amber-600">This employee has no line manager set — pick one above.</p>
                                @endunless
                            </div>

                            <label class="flex cursor-pointer select-none items-start gap-3">
                                <input type="radio" name="manager_mode" value="none" x-model="mode" class="mt-0.5 border-slate-300 text-brand-500 focus:ring-brand-500">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Leave it unsigned for now</span>
                            </label>
                        </div>
                    @endif
                </div>

                <div class="shrink-0 p-5 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-3">
                    <button type="button" @click="sendOpen = false" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition"><i data-lucide="send" class="h-4 w-4"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Signature pad for the sender countersigning before the document goes out.
    // Same approach as the employee signing page: draw or type, kept as a PNG
    // data URI in a hidden field.
    function adminSignPad() {
        return {
            // A refused send comes back here — keep the choice they made.
            mode: '{{ old('manager_mode', $hasManagerField && $defaultManagerId ? 'manager' : 'none') }}',
            image: '',
            typed: '',
            init() {
                this.$watch('mode', (m) => { if (m === 'self') this.$nextTick(() => this.attach()); });
            },
            attach() {
                const canvas = this.$refs.pad;
                if (!canvas || canvas.dataset.ready) return;
                canvas.dataset.ready = '1';
                const ctx = canvas.getContext('2d');
                ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
                let drawing = false;
                const pos = (e) => {
                    const r = canvas.getBoundingClientRect(), t = e.touches ? e.touches[0] : e;
                    return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
                };
                const start = (e) => { e.preventDefault(); drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); };
                const move  = (e) => { if (!drawing) return; e.preventDefault(); const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); };
                const end   = () => { if (drawing) { drawing = false; this.typed = ''; this.image = canvas.toDataURL('image/png'); } };
                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                window.addEventListener('mouseup', end);
                canvas.addEventListener('touchstart', start, { passive: false });
                canvas.addEventListener('touchmove', move, { passive: false });
                canvas.addEventListener('touchend', end);
            },
            renderTyped() {
                const canvas = this.$refs.pad;
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const name = this.typed.trim();
                if (!name) { this.image = ''; return; }
                ctx.fillStyle = '#0f172a';
                ctx.font = 'italic 46px Georgia, serif';
                ctx.fillText(name, 18, canvas.height / 2 + 14);
                this.image = canvas.toDataURL('image/png');
            },
            clearPad() {
                const canvas = this.$refs.pad;
                if (canvas) canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                this.image = ''; this.typed = '';
            },
        };
    }
</script>
@endsection
