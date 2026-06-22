@extends('layouts.hr-app')

@section('title', 'My Policies')
@section('breadcrumb', 'My Policies')

@php
    $ackBadge = ['pending' => 'bg-amber-50 text-amber-700', 'viewed' => 'bg-sky-50 text-sky-700', 'acknowledged' => 'bg-emerald-50 text-emerald-700'];
    $pendingCount = $acks->where('status', '!=', 'acknowledged')->count();
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{ tab: 'pending' }">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">My Policies</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review and acknowledge company policies assigned to you.</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
        <button @click="tab = 'pending'" :class="tab === 'pending' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">To do @if($pendingCount)<span class="ml-1 inline-flex items-center justify-center rounded-full bg-amber-100 text-amber-700 px-1.5 text-[10px]">{{ $pendingCount }}</span>@endif</button>
        <button @click="tab = 'acknowledged'" :class="tab === 'acknowledged' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">Acknowledged</button>
        <button @click="tab = 'all'" :class="tab === 'all' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">All</button>
    </div>

    <div class="space-y-3">
        @forelse($acks as $ack)
            @php $isAck = $ack->status === 'acknowledged'; @endphp
            <div x-show="tab === 'all' || (tab === 'acknowledged' && {{ $isAck ? 'true' : 'false' }}) || (tab === 'pending' && {{ $isAck ? 'false' : 'true' }})"
                 class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 shrink-0 dark:bg-brand-500/10"><i data-lucide="book-text" class="h-5 w-5"></i></div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('policies.view', $ack->policy) }}" class="text-base font-bold text-slate-800 hover:text-brand-600 dark:text-white">{{ optional($ack->policy)->title }}</a>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ackBadge[$ack->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($ack->status) }}</span>
                            </div>
                            @if(optional($ack->policy)->description)<p class="text-xs text-slate-400 mt-1 line-clamp-2">{{ $ack->policy->description }}</p>@endif
                            <p class="text-xs text-slate-400 mt-1">
                                {{ optional($ack->policy)->category_label }} · v{{ optional($ack->policy)->version }}
                                @if($isAck && $ack->acknowledged_at) · Acknowledged {{ $ack->acknowledged_at->format('d M Y') }}@endif
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <a href="{{ route('policies.view', $ack->policy) }}" class="inline-flex items-center gap-1.5 rounded-xl {{ $isAck ? 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200' : 'bg-brand-600 text-slate-900 hover:bg-brand-700' }} px-4 py-2 text-sm font-bold">
                            {{ $isAck ? 'View' : 'Review & sign' }}
                        </a>
                    </div>
                </div>
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
