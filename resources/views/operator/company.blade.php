@extends('layouts.operator')

@section('title', $tenant->name)
@section('breadcrumb', 'Companies · ' . $tenant->name)

@php
    $badge = [
        'active'=>'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'trialing'=>'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',
        'suspended'=>'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'canceled'=>'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
    ][$tenant->status] ?? 'bg-slate-100 text-slate-600';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('operator.index') }}" class="inline-grid place-items-center h-9 w-9 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-4 w-4"></i></a>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $tenant->brand_name ?: $tenant->name }}</h1>
                <p class="text-[11px] font-mono text-slate-400">{{ $tenant->slug }}</p>
            </div>
            <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-bold {{ $badge }}">{{ ucfirst($tenant->status) }}</span>
        </div>
        @if($tenant->admin)
            <form action="{{ route('operator.impersonate', $tenant) }}" method="POST" onsubmit="return confirm('Log in as {{ $tenant->name }}\'s admin?');">@csrf
                <button class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="log-in" class="h-4 w-4"></i> Impersonate</button>
            </form>
        @endif
    </div>

    {{-- Overview --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-xl border border-slate-200/80 bg-white p-3 dark:bg-slate-800 dark:border-slate-700"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Plan</p><p class="font-bold text-slate-800 dark:text-white mt-0.5">{{ $tenant->planConfig()['name'] ?? '—' }}</p></div>
        <div class="rounded-xl border border-slate-200/80 bg-white p-3 dark:bg-slate-800 dark:border-slate-700"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Price / mo</p>
            <p class="font-bold text-slate-800 dark:text-white mt-0.5">
                @if($tenant->discount_percent)<span class="line-through text-slate-400 text-xs">{{ $symbol }}{{ number_format($tenant->planPrice(),2) }}</span> @endif{{ $symbol }}{{ number_format($tenant->effectivePrice(),2) }}
                @if($tenant->discount_percent)<span class="text-[10px] font-bold text-emerald-600">-{{ $tenant->discount_percent }}%</span>@endif
            </p></div>
        <div class="rounded-xl border border-slate-200/80 bg-white p-3 dark:bg-slate-800 dark:border-slate-700"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Seats</p><p class="font-bold text-slate-800 dark:text-white mt-0.5">{{ $tenant->seatCount() }} / {{ $tenant->seatLimit()===0 ? '∞' : $tenant->seatLimit() }}</p></div>
        <div class="rounded-xl border border-slate-200/80 bg-white p-3 dark:bg-slate-800 dark:border-slate-700"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Joined</p><p class="font-bold text-slate-800 dark:text-white mt-0.5">{{ optional($tenant->created_at)->format('d M Y') }}</p></div>
    </div>
    @if($tenant->admin)<p class="text-xs text-slate-400">Admin: <b class="text-slate-600 dark:text-slate-300">{{ $tenant->admin->full_name }}</b> · {{ $tenant->admin->email }}</p>@endif

    {{-- Subscription controls --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-5 space-y-5 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2"><i data-lucide="credit-card" class="h-4 w-4 text-indigo-500"></i> Subscription</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            @php $isOwner = auth()->user()->isOperatorOwner(); @endphp
            {{-- Change plan (owner only) --}}
            @if($isOwner)
            <form action="{{ route('operator.plan', $tenant) }}" method="POST" class="space-y-2">@csrf
                <label class="block text-[11px] font-bold text-slate-500">Plan</label>
                <div class="flex gap-2">
                    <select name="plan" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach($plans as $p)<option value="{{ $p->key }}" @selected($tenant->planKey()===$p->key)>{{ $p->name }} — {{ $symbol }}{{ number_format($p->price,0) }}</option>@endforeach
                    </select>
                    <button class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-bold text-white hover:bg-indigo-700">Change</button>
                </div>
            </form>

            {{-- Discount (owner only) --}}
            <form action="{{ route('operator.companies.discount', $tenant) }}" method="POST" class="space-y-2">@csrf
                <label class="block text-[11px] font-bold text-slate-500">Discount / comp (%)</label>
                <div class="flex gap-2">
                    <input type="number" name="discount_percent" min="0" max="100" value="{{ $tenant->discount_percent ?? 0 }}" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <button class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Apply</button>
                </div>
            </form>
            @endif

            {{-- Extend trial --}}
            <form action="{{ route('operator.companies.trial', $tenant) }}" method="POST" class="space-y-2">@csrf
                <label class="block text-[11px] font-bold text-slate-500">Extend / start trial
                    @if($tenant->onTrial())<span class="text-indigo-500">· ends {{ $tenant->trial_ends_at->format('d M') }} ({{ $tenant->trialDaysLeft() }}d)</span>@endif
                </label>
                <div class="flex gap-2">
                    <input type="number" name="days" min="1" max="365" value="14" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <button class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">+ Days</button>
                </div>
            </form>

            {{-- Lifecycle --}}
            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-500">Status</label>
                <div class="flex flex-wrap gap-2">
                    @if($tenant->status==='suspended')
                        <form action="{{ route('operator.activate', $tenant) }}" method="POST">@csrf<button class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-700">Activate</button></form>
                    @elseif($isOwner)
                        <form action="{{ route('operator.suspend', $tenant) }}" method="POST" onsubmit="return confirm('Suspend {{ $tenant->name }}? Their users are locked out.');">@csrf<button class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-bold text-white hover:bg-amber-600">Suspend</button></form>
                    @endif
                    @if($tenant->isCanceled())
                        <form action="{{ route('operator.companies.reactivate', $tenant) }}" method="POST">@csrf<button class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-700">Reactivate</button></form>
                    @elseif($isOwner)
                        <form action="{{ route('operator.companies.cancel', $tenant) }}" method="POST" onsubmit="return confirm('Cancel {{ $tenant->name }}\'s subscription?');">@csrf<button class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Cancel</button></form>
                    @endif
                    @if(!$isOwner)<span class="text-[11px] text-slate-400 self-center">Support role — pricing &amp; suspend/cancel are owner-only.</span>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- History --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Subscription history</h2></div>
        <div class="divide-y divide-slate-50 dark:divide-slate-700/40">
            @forelse($events as $e)
                <div class="flex items-center gap-3 px-5 py-3">
                    <span class="grid place-items-center h-8 w-8 shrink-0 rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300"><i data-lucide="{{ $e->icon }}" class="h-4 w-4"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-800 dark:text-white">{{ $e->description }}</p>
                        <p class="text-[11px] text-slate-400">{{ $e->created_at->format('d M Y · H:i') }}@if($e->operator) · by {{ $e->operator->full_name }}@endif</p>
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">No subscription activity yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
