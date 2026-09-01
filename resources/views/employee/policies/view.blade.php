@extends('layouts.hr-app')

@section('title', $policy->title)
@section('breadcrumb', 'My Policies')

@php
    $isAck = $ack && $ack->status === 'acknowledged';
    $needsSig = $policy->requires_signature;
@endphp

@section('content')
<style>[x-cloak]{display:none!important}
    .policy-content h1{font-size:1.5rem;font-weight:800;margin:1rem 0 .5rem}
    .policy-content h2{font-size:1.25rem;font-weight:700;margin:1rem 0 .5rem}
    .policy-content h3{font-size:1.1rem;font-weight:700;margin:.75rem 0 .5rem}
    .policy-content p{margin:.5rem 0;line-height:1.7}
    .policy-content ul{list-style:disc;padding-left:1.5rem;margin:.5rem 0}
    .policy-content ol{list-style:decimal;padding-left:1.5rem;margin:.5rem 0}
    .policy-content a{color:#2563eb;text-decoration:underline}
</style>

<div class="max-w-3xl mx-auto space-y-6">
    <a href="{{ route('my-policies.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> My policies</a>

    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <!-- Header -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $policy->title }}</h1>
            <span class="text-[11px] font-semibold text-slate-400">v{{ $policy->version }}</span>
            @if($isAck)<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-0.5 text-[11px] font-bold"><i data-lucide="check" class="h-3 w-3"></i> Acknowledged</span>@endif
        </div>
        @if($policy->description)<p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">{{ $policy->description }}</p>@endif
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400 mt-3">
            <span class="font-semibold text-slate-500">{{ $policy->category_label }}</span>
            @if($policy->effective_date)<span>Effective {{ $policy->effective_date->format('d M Y') }}</span>@endif
        </div>
    </div>

    <!-- Document -->
    @if($policy->document_file)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 shrink-0"><i data-lucide="file-text" class="h-5 w-5"></i></div>
                <div class="min-w-0"><p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $policy->document_filename }}</p><p class="text-xs text-slate-400">{{ $policy->document_size_label }}</p></div>
            </div>
            <a href="{{ route('policies.download', $policy) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="download" class="h-4 w-4"></i> Download</a>
        </div>
    @endif

    <!-- Content -->
    @if($policy->content)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
            <div class="policy-content text-sm text-slate-700 dark:text-slate-200">{!! $policy->content !!}</div>
        </div>
    @endif

    <!-- Acknowledge -->
    @if($isAck)
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">
            <p class="font-bold flex items-center gap-2"><i data-lucide="check-circle-2" class="h-5 w-5"></i> You acknowledged this policy on {{ optional($ack->acknowledged_at)->format('d M Y \a\t H:i') }}.</p>
            @if($ack->signature_type === 'typed' && $ack->signature_name)<p class="mt-1.5 pl-7">Signed: <span class="font-semibold italic">{{ $ack->signature_name }}</span></p>@endif
            @if($ack->signature_type === 'drawn' && $ack->signature_data)<img src="{{ $ack->signature_data }}" alt="Signature" class="mt-2 ml-7 h-16 bg-white rounded border border-emerald-200 p-1">@endif
        </div>
    @elseif($policy->requires_acknowledgment)
        <form method="POST" action="{{ route('policies.acknowledge', $policy) }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4"
              x-data="ackForm()" @submit="return onSubmit($event)">
            @csrf
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Acknowledgment</h2>

            @if($needsSig)
                <div>
                    <div class="flex items-center gap-1 mb-3 rounded-xl bg-slate-100 p-1 w-max dark:bg-slate-900">
                        <button type="button" @click="mode='typed'" :class="mode==='typed' ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500'" class="rounded-lg px-3 py-1.5 text-xs font-bold transition">Type</button>
                        <button type="button" @click="mode='drawn'" :class="mode==='drawn' ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500'" class="rounded-lg px-3 py-1.5 text-xs font-bold transition">Draw</button>
                    </div>
                    <input type="hidden" name="signature_type" :value="mode">

                    <div x-show="mode==='typed'">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type your full name</label>
                        <input type="text" name="signature_name" x-model="typedName" placeholder="e.g. {{ auth()->user()->full_name }}" class="w-full rounded-xl border-slate-300 text-base font-signature italic dark:bg-slate-900 dark:border-slate-600 dark:text-white" style="font-family: 'Brush Script MT', cursive;">
                    </div>

                    <div x-show="mode==='drawn'" x-cloak>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Draw your signature</label>
                        <div class="rounded-xl border border-slate-300 dark:border-slate-600 bg-white relative">
                            <canvas x-ref="canvas" class="w-full rounded-xl touch-none" style="height:140px"></canvas>
                            <button type="button" @click="clearPad()" class="absolute top-2 right-2 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600 hover:bg-slate-200">Clear</button>
                        </div>
                        <input type="hidden" name="signature_data" x-ref="sigData">
                    </div>
                </div>
            @endif

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="confirmed" value="1" x-model="confirmed" class="mt-0.5 rounded border-slate-300 text-brand-600 h-5 w-5">
                <span class="text-sm text-slate-700 dark:text-slate-200">I confirm that I have read and understood the <span class="font-semibold">{{ $policy->title }}</span> policy{{ $needsSig ? ' and the signature above is mine' : '' }}.</span>
            </label>

            <button type="submit" class="w-full rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-slate-900 hover:bg-brand-700">Acknowledge policy</button>
        </form>
    @else
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-sm text-slate-500 dark:bg-slate-800 dark:border-slate-700">This policy is for your information — no acknowledgment is required.</div>
    @endif
</div>

@if(!$isAck && $policy->requires_acknowledgment && $needsSig)
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
@endif
<script>
    function ackForm() {
        return {
            mode: 'typed',
            typedName: '',
            confirmed: false,
            pad: null,
            init() {
                @if($needsSig)
                this.$nextTick(() => this.setupPad());
                this.$watch('mode', () => { if (this.mode === 'drawn') this.$nextTick(() => this.setupPad()); });
                @endif
            },
            setupPad() {
                const canvas = this.$refs.canvas;
                if (!canvas || this.pad || typeof SignaturePad === 'undefined') return;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                this.pad = new SignaturePad(canvas, { penColor: '#0f172a' });
            },
            clearPad() { if (this.pad) this.pad.clear(); },
            onSubmit(e) {
                if (!this.confirmed) { e.preventDefault(); alert('Please confirm you have read the policy.'); return false; }
                @if($needsSig)
                if (this.mode === 'typed' && !this.typedName.trim()) { e.preventDefault(); alert('Please type your name.'); return false; }
                if (this.mode === 'drawn') {
                    if (!this.pad || this.pad.isEmpty()) { e.preventDefault(); alert('Please draw your signature.'); return false; }
                    this.$refs.sigData.value = this.pad.toDataURL('image/png');
                }
                @endif
                return true;
            },
        };
    }
</script>
@endsection
