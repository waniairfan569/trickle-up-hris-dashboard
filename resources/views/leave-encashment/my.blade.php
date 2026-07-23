@extends('layouts.hr-app')

@section('title', 'My Leave Encashments')
@section('breadcrumb', 'My Encashments')

@section('content')
@php $fmtD = fn ($d) => rtrim(rtrim(number_format((float) $d, 1), '0'), '.'); @endphp
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="banknote" class="h-6 w-6 text-brand-500"></i> My Leave Encashments
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Your year-end leave encashment history, with the full breakdown per year.</p>
    </div>

    @forelse($records as $yr => $items)
        <div class="space-y-3">
            <h2 class="text-sm font-extrabold text-slate-700 dark:text-slate-200">{{ $yr }}–{{ substr($yr + 1, 2) }}</h2>
            @foreach($items as $r)
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-extrabold text-slate-900 dark:text-white">{{ optional($r->policy)->name }}</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ $r->leave_year_label }}@if($r->is_pro_rata) · pro-rata ({{ $r->pro_rata_months }} months)@endif</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold shrink-0 {{ $r->status_badge_color }}">{{ ucfirst($r->status) }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div><p class="text-slate-400 font-bold uppercase text-[9px]">Allocation</p><p class="font-bold text-slate-700 dark:text-slate-200 mt-0.5">{{ $fmtD($r->annual_allocation) }} days</p></div>
                        <div><p class="text-slate-400 font-bold uppercase text-[9px]">Remaining at year end</p><p class="font-bold text-slate-700 dark:text-slate-200 mt-0.5">{{ $fmtD($r->days_remaining_before_renewal) }} days</p></div>
                        <div><p class="text-slate-400 font-bold uppercase text-[9px]">Encashment cap</p><p class="font-bold text-slate-700 dark:text-slate-200 mt-0.5">{{ $fmtD($r->encashment_cap_days) }} days</p></div>
                        <div><p class="text-slate-400 font-bold uppercase text-[9px]">Days encashed</p><p class="font-bold text-emerald-600 mt-0.5">{{ $fmtD($r->days_to_encash) }} days</p></div>
                    </div>
                    <div class="mt-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 p-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span class="text-slate-500">Daily rate: <b>PKR {{ number_format($r->daily_rate, 2) }}</b> ({{ $fmtD($r->days_to_encash) }} × rate)</span>
                        <span class="text-base font-extrabold text-slate-900 dark:text-white">{{ $r->formatted_amount }}</span>
                    </div>
                    @if($r->days_lapsed > 0)
                        <p class="mt-2 text-[11px] font-bold text-amber-600">⚠ {{ $fmtD($r->days_lapsed) }} day(s) lapsed (above the cap)</p>
                    @endif
                    @if($r->status === 'paid' && $r->payment_date)
                        <p class="mt-2 text-[11px] text-emerald-600 font-semibold">Paid {{ $r->payment_date->format('d M Y') }}{{ $r->payment_reference ? ' · ref ' . $r->payment_reference : '' }}</p>
                    @elseif($r->status === 'rejected' && $r->admin_notes)
                        <p class="mt-2 text-[11px] text-rose-600">Rejected: {{ $r->admin_notes }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="banknote" class="h-7 w-7"></i></div>
            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No encashments yet</p>
            <p class="text-xs text-slate-400 mt-1">When your leave year renews, any encashment will appear here.</p>
        </div>
    @endforelse
</div>
@endsection
