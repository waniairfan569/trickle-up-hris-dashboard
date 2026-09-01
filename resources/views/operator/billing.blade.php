@extends('layouts.operator')

@section('title', 'Billing')
@section('breadcrumb', 'Billing')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="receipt" class="h-6 w-6 text-indigo-500"></i> Billing
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Revenue, subscription health and recent activity across the platform.</p>
    </div>

    {{-- Headline metrics --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-indigo-200/60 bg-indigo-50/50 p-4 dark:bg-indigo-500/10 dark:border-indigo-500/20">
            <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-500">MRR</p>
            <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1 tabular-nums">{{ $symbol }}{{ number_format($stats['mrr'], 2) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">ARR (est.)</p>
            <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1 tabular-nums">{{ $symbol }}{{ number_format($stats['arr'], 0) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Active</p>
            <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">{{ $stats['active'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Trialing</p>
            <p class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1 tabular-nums">{{ $stats['trialing'] }}</p>
        </div>
    </div>
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Suspended</p>
            <p class="text-xl font-extrabold text-amber-600 dark:text-amber-400 mt-1 tabular-nums">{{ $stats['suspended'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Canceled (churn)</p>
            <p class="text-xl font-extrabold text-rose-600 dark:text-rose-400 mt-1 tabular-nums">{{ $stats['canceled'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Discounted</p>
            <p class="text-xl font-extrabold text-slate-700 dark:text-slate-200 mt-1 tabular-nums">{{ $stats['discounted'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Revenue by plan --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Revenue by plan</h2></div>
            <div class="p-2">
                @forelse($byPlan as $row)
                    <div class="flex items-center justify-between px-3 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-800 dark:text-white">{{ $row['name'] }}</span>
                            <span class="text-[11px] text-slate-400">{{ $row['count'] }} compan{{ $row['count']===1?'y':'ies' }}</span>
                        </div>
                        <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ $symbol }}{{ number_format($row['mrr'], 2) }}</span>
                    </div>
                @empty
                    <p class="px-3 py-6 text-center text-sm text-slate-400">No active revenue yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Trials ending soon --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Trials ending soon</h2></div>
            <div class="p-2">
                @forelse($trialsEnding as $t)
                    <a href="{{ route('operator.companies.show', $t) }}" class="flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <span class="font-semibold text-slate-800 dark:text-white">{{ $t->brand_name ?: $t->name }}</span>
                        <span class="text-xs font-bold {{ $t->trialDaysLeft() <= 2 ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $t->trialDaysLeft() }}d left</span>
                    </a>
                @empty
                    <p class="px-3 py-6 text-center text-sm text-slate-400">No trials ending in the next 7 days.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Recent subscription activity</h2></div>
        <div class="divide-y divide-slate-50 dark:divide-slate-700/40">
            @forelse($recent as $e)
                <div class="flex items-center gap-3 px-5 py-3">
                    <span class="grid place-items-center h-8 w-8 shrink-0 rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300"><i data-lucide="{{ $e->icon }}" class="h-4 w-4"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-800 dark:text-white"><b>{{ optional($e->tenant)->name ?? '—' }}</b> · {{ $e->description }}</p>
                        <p class="text-[11px] text-slate-400">{{ $e->created_at->format('d M Y · H:i') }}@if($e->operator) · by {{ $e->operator->full_name }}@endif</p>
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
