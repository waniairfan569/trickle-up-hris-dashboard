@extends('layouts.hr-app')

@section('title', 'My Policies')
@section('breadcrumb', 'My Policies')

@php
    $ackBadge = ['pending' => 'bg-amber-50 text-amber-700', 'viewed' => 'bg-sky-50 text-sky-700', 'acknowledged' => 'bg-emerald-50 text-emerald-700'];
    $pendingCount = $acks->where('status', '!=', 'acknowledged')->count();
@endphp

@section('content')
<style>
    .policy-content h1{font-size:1.4rem;font-weight:800;margin:1rem 0 .5rem}
    .policy-content h2{font-size:1.2rem;font-weight:700;margin:1rem 0 .5rem}
    .policy-content h3{font-size:1.05rem;font-weight:700;margin:.75rem 0 .5rem}
    .policy-content p{margin:.5rem 0;line-height:1.7}
    .policy-content ul{list-style:disc;padding-left:1.5rem;margin:.5rem 0}
    .policy-content ol{list-style:decimal;padding-left:1.5rem;margin:.5rem 0}
    .policy-content a{color:#2563eb;text-decoration:underline}
</style>
<div class="space-y-6" x-data="{ tab: 'all' }">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">My Policies</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review and acknowledge company policies assigned to you.</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
        <button @click="tab = 'all'" :class="tab === 'all' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">All</button>
        <button @click="tab = 'pending'" :class="tab === 'pending' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">To do @if($pendingCount)<span class="ml-1 inline-flex items-center justify-center rounded-full bg-amber-100 text-amber-700 px-1.5 text-[10px]">{{ $pendingCount }}</span>@endif</button>
        <button @click="tab = 'acknowledged'" :class="tab === 'acknowledged' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">Acknowledged</button>
    </div>

    <div class="space-y-3">
        @forelse($acks as $ack)
            @php $isAck = $ack->status === 'acknowledged'; $policy = $ack->policy; @endphp
            <div x-show="tab === 'all' || (tab === 'acknowledged' && {{ $isAck ? 'true' : 'false' }}) || (tab === 'pending' && {{ $isAck ? 'false' : 'true' }})"
                 x-data="{ openView: false }"
                 class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 shrink-0 dark:bg-brand-500/10"><i data-lucide="book-text" class="h-5 w-5"></i></div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($isAck)
                                    <button type="button" @click="openView = true" class="text-base font-bold text-slate-800 hover:text-brand-600 dark:text-white text-left">{{ optional($policy)->title }}</button>
                                @else
                                    <a href="{{ route('policies.view', $policy) }}" class="text-base font-bold text-slate-800 hover:text-brand-600 dark:text-white">{{ optional($policy)->title }}</a>
                                @endif
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ackBadge[$ack->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($ack->status) }}</span>
                            </div>
                            @if(optional($policy)->description)<p class="text-xs text-slate-400 mt-1 line-clamp-2">{{ $policy->description }}</p>@endif
                            <p class="text-xs text-slate-400 mt-1">
                                {{ optional($policy)->category_label }} · v{{ optional($policy)->version }}
                                @if($isAck && $ack->acknowledged_at) · Acknowledged {{ $ack->acknowledged_at->format('d M Y') }}@endif
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0">
                        @if($isAck)
                            <button type="button" @click="openView = true" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 px-4 py-2 text-sm font-bold">View</button>
                        @else
                            <a href="{{ route('policies.view', $policy) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 text-slate-900 hover:bg-brand-700 px-4 py-2 text-sm font-bold">Review &amp; sign</a>
                        @endif
                    </div>
                </div>

                {{-- Read-only policy modal (acknowledged policies open here instead of a separate page) --}}
                @if($isAck)
                    <template x-teleport="body">
                        <div x-show="openView" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="openView = false">
                            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="openView = false"></div>
                            <div class="relative w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 shadow-xl">
                                <div class="sticky top-0 z-10 flex items-start justify-between gap-3 px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 bg-white dark:bg-slate-800">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white truncate">{{ optional($policy)->title }}</h3>
                                            <span class="text-[11px] font-semibold text-slate-400">v{{ optional($policy)->version }}</span>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 text-[10px] font-bold dark:bg-emerald-500/10 dark:text-emerald-400"><i data-lucide="check" class="h-3 w-3"></i> Acknowledged</span>
                                        </div>
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ optional($policy)->category_label }}@if(optional($policy)->effective_date) · Effective {{ $policy->effective_date->format('d M Y') }}@endif</p>
                                    </div>
                                    <button type="button" @click="openView = false" class="shrink-0 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="h-5 w-5"></i></button>
                                </div>

                                <div class="p-6 space-y-5">
                                    @if(optional($policy)->description)<p class="text-sm text-slate-500 dark:text-slate-400">{{ $policy->description }}</p>@endif

                                    @if(optional($policy)->document_file)
                                        <a href="{{ route('policies.download', $policy) }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 dark:border-slate-700 p-3 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                            <span class="flex items-center gap-3 min-w-0">
                                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 text-rose-600 shrink-0"><i data-lucide="file-text" class="h-4 w-4"></i></span>
                                                <span class="min-w-0"><span class="block text-sm font-bold text-slate-800 dark:text-white truncate">{{ $policy->document_filename }}</span><span class="block text-xs text-slate-400">{{ $policy->document_size_label }}</span></span>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-600"><i data-lucide="download" class="h-4 w-4"></i> Download</span>
                                        </a>
                                    @endif

                                    @if(optional($policy)->content)
                                        <div class="policy-content text-sm text-slate-700 dark:text-slate-200">{!! $policy->content !!}</div>
                                    @endif

                                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">
                                        <p class="font-bold flex items-center gap-2"><i data-lucide="check-circle-2" class="h-5 w-5"></i> You acknowledged this policy on {{ optional($ack->acknowledged_at)->format('d M Y \a\t H:i') }}.</p>
                                        @if($ack->signature_type === 'typed' && $ack->signature_name)<p class="mt-1.5 pl-7">Signed: <span class="font-semibold italic">{{ $ack->signature_name }}</span></p>@endif
                                        @if($ack->signature_type === 'drawn' && $ack->signature_data)<img src="{{ $ack->signature_data }}" alt="Signature" class="mt-2 ml-7 h-16 bg-white rounded border border-emerald-200 p-1">@endif
                                    </div>
                                </div>

                                <div class="sticky bottom-0 flex justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-white dark:bg-slate-800">
                                    @if(optional($policy)->document_file)<a href="{{ route('policies.download', $policy) }}" class="btn-outline btn-sm"><i data-lucide="download" class="h-4 w-4"></i> Download</a>@endif
                                    <button type="button" @click="openView = false" class="btn-brand btn-sm">Close</button>
                                </div>
                            </div>
                        </div>
                    </template>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="book-check" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No policies assigned</p>
                <p class="text-xs text-slate-400 mt-1">You're all caught up.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
