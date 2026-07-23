@extends('layouts.hr-app')

@section('title', 'Renewal Preview')
@section('breadcrumb', 'Leave Year & Encashment')

@section('content')
@php $fmtD = fn ($d) => rtrim(rtrim(number_format((float) $d, 1), '0'), '.'); @endphp
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <a href="{{ route('leave-year-settings.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All settings</a>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white mt-2">Renewal Preview — {{ optional($setting->policy)->name }} · {{ $setting->getCurrentYearLabel() }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><b>Dry run — no changes have been made.</b> Rule: {{ $setting->encashmentRuleLabel() }}. Review, then confirm below.</p>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            ['Total employees', $summary['total'], 'users', 'text-slate-800 dark:text-white'],
            ['Will get encashment', $summary['with_encashment'], 'banknote', 'text-emerald-600'],
            ['Nothing remaining', $summary['zero_remaining'], 'check-circle', 'text-slate-500'],
            ['Total amount', 'PKR ' . number_format($summary['total_amount'], 0), 'wallet', 'text-brand-600'],
        ] as [$label, $val, $icon, $cls])
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1"><i data-lucide="{{ $icon }}" class="h-3.5 w-3.5"></i> {{ $label }}</p>
                <p class="text-xl font-extrabold mt-1 {{ $cls }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>
    @if($summary['total_lapsed'] > 0)
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300">⚠ <b>{{ $fmtD($summary['total_lapsed']) }} day(s)</b> in total will lapse (remaining above each employee's cap).</div>
    @endif

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-left font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-900/40">
                    <th class="px-4 py-2.5">Employee</th><th class="px-4 py-2.5">Dept</th>
                    <th class="px-4 py-2.5 text-right">Allocation</th><th class="px-4 py-2.5 text-right">Used</th>
                    <th class="px-4 py-2.5 text-right">Remaining</th><th class="px-4 py-2.5 text-right">Cap</th>
                    <th class="px-4 py-2.5 text-right">Encash</th><th class="px-4 py-2.5 text-right">Lapsed</th>
                    <th class="px-4 py-2.5 text-right">Carry</th><th class="px-4 py-2.5 text-right">New balance</th>
                    <th class="px-4 py-2.5 text-right">Amount</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($rows as $r)
                        @php
                            $rowCls = $r['days_to_encash'] > 0
                                ? 'bg-emerald-50/40 dark:bg-emerald-500/5'
                                : ($r['days_remaining'] <= 0 ? 'text-slate-400' : ($r['days_lapsed'] > 0 ? 'bg-amber-50/40 dark:bg-amber-500/5' : ''));
                        @endphp
                        <tr class="{{ $rowCls }}">
                            <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">{{ $r['employee']->full_name }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ optional($r['employee']->department)->name }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $fmtD($r['allocation']) }}@if($r['is_pro_rata']) <span class="text-[9px] font-bold text-indigo-500">PRO-RATA</span>@endif</td>
                            <td class="px-4 py-2.5 text-right">{{ $fmtD($r['used']) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ $fmtD($r['days_remaining']) }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $fmtD($r['encashment_cap']) }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-emerald-600">{{ $fmtD($r['days_to_encash']) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $r['days_lapsed'] > 0 ? 'font-bold text-amber-600' : '' }}">{{ $fmtD($r['days_lapsed']) }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $fmtD($r['carry_forward']) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ $fmtD($r['new_balance']) }}</td>
                            <td class="px-4 py-2.5 text-right font-bold">PKR {{ number_format($r['encashment_amount'], 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-10 text-center text-slate-400">No employees found on this policy.</td></tr>
                    @endforelse
                </tbody>
                @if(count($rows))
                    <tfoot><tr class="border-t-2 border-slate-200 dark:border-slate-600 font-extrabold text-slate-800 dark:text-white">
                        <td class="px-4 py-2.5" colspan="6">Totals</td>
                        <td class="px-4 py-2.5 text-right text-emerald-600">{{ $fmtD(collect($rows)->sum('days_to_encash')) }}</td>
                        <td class="px-4 py-2.5 text-right text-amber-600">{{ $fmtD($summary['total_lapsed']) }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $fmtD(collect($rows)->sum('carry_forward')) }}</td>
                        <td class="px-4 py-2.5"></td>
                        <td class="px-4 py-2.5 text-right">PKR {{ number_format($summary['total_amount'], 0) }}</td>
                    </tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Confirm --}}
    <form method="POST" action="{{ route('leave-year-settings.renew', $setting) }}" x-data="{ confirm: '' }"
          class="bg-white rounded-2xl border border-rose-200 dark:border-rose-500/30 shadow-sm p-6 dark:bg-slate-800 flex flex-col sm:flex-row sm:items-end gap-3">
        @csrf
        <div class="flex-1">
            <p class="text-sm font-bold text-slate-800 dark:text-white">Run this renewal for real?</p>
            <p class="text-xs text-slate-400 mt-0.5 mb-2">Creates encashment records, lapses days above the cap, and opens the new leave-year balances. Type <b>RENEW</b> to confirm.</p>
            <input type="text" name="confirm_text" x-model="confirm" placeholder="Type RENEW" class="w-48 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-bold uppercase dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <button type="submit" :disabled="confirm !== 'RENEW'" :class="confirm !== 'RENEW' && 'opacity-50 cursor-not-allowed'"
                class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-rose-700">
            <i data-lucide="play" class="h-4 w-4"></i> Confirm &amp; Run Renewal
        </button>
    </form>
</div>
@endsection
