@extends('layouts.hr-app')

@section('title', 'Leave Balances')
@section('breadcrumb', 'Leave Balances')

@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $granted = fn ($b) => (float) $b->opening_balance + (float) $b->accrued + (float) $b->carried_over + (float) $b->adjusted;
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{ q: '' }">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="table-2" class="h-6 w-6 text-brand-500"></i> Leave Balances
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Every employee’s remaining leave in each category — <span class="font-semibold">remaining</span> of allocated days for {{ $year }}.</p>
        </div>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('time-off-policies.balances-overview') }}">
                <select name="year" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ (int) $y === (int) $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <form method="POST" action="{{ route('time-off-policies.recompute-balances') }}"
                  onsubmit="return confirm('Recalculate every opening balance for {{ $year }} using each policy&#39;s pro-rata rule? Mid-year joiners drop to their pro-rata allocation; used/pending days are untouched.');">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-400">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Recalculate
                </button>
            </form>
            <a href="{{ route('time-off-policies.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200">
                <i data-lucide="settings-2" class="h-4 w-4"></i> Policies
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm font-medium text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}
        </div>
    @endif

    <div class="relative max-w-sm">
        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
        <input type="text" x-model="q" placeholder="Search employee…" class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-4 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
    </div>

    @if($employees->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="calendar-off" class="h-7 w-7"></i></div>
            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No leave balances for {{ $year }}</p>
            <p class="text-xs text-slate-400 mt-1">Balances are created when employees are assigned leave policies.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/40 px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Employee</th>
                            @foreach($policies as $policy)
                                <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                                    <a href="{{ route('time-off-policies.balances', ['time_off_policy' => $policy->id, 'year' => $year]) }}" class="hover:text-brand-600" title="Open {{ $policy->name }} balances">{{ $policy->name }}</a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($employees as $emp)
                            @php $name = trim($emp->first_name . ' ' . $emp->last_name) ?: 'Employee'; @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20"
                                x-show="q === '' || @js(strtolower($name)).includes(q.toLowerCase())">
                                <td class="sticky left-0 z-10 bg-white dark:bg-slate-800 px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        @if($emp->avatar_url)
                                            <img src="{{ $emp->avatar_url }}" class="h-8 w-8 rounded-lg object-cover" alt="">
                                        @else
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-indigo-500 text-[11px] font-bold text-white">{{ $emp->initials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('employees.profile', $emp->id) }}" class="block text-sm font-bold text-slate-800 dark:text-white hover:text-brand-600 truncate">{{ $name }}</a>
                                            @if($emp->job_title)<span class="block text-[11px] text-slate-400 truncate">{{ $emp->job_title }}</span>@endif
                                        </div>
                                    </div>
                                </td>
                                @foreach($policies as $policy)
                                    @php $b = $balances[$emp->id][$policy->id] ?? null; @endphp
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @if($b)
                                            @php $rem = (float) $b->remaining; $tot = $granted($b); @endphp
                                            <span class="text-sm font-extrabold {{ $rem <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-white' }}">{{ $fmt($rem) }}</span>
                                            <span class="text-[11px] text-slate-400"> / {{ $fmt($tot) }}</span>
                                            @if((float) $b->pending > 0)
                                                <span class="block text-[10px] font-bold text-amber-600 dark:text-amber-400">{{ $fmt($b->pending) }} pending</span>
                                            @endif
                                        @else
                                            <span class="text-slate-300 dark:text-slate-600">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-slate-700">
                        <tr>
                            <td class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/40 px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">Total remaining</td>
                            @foreach($policies as $policy)
                                @php $colTotal = $employees->sum(fn ($e) => optional($balances[$e->id][$policy->id] ?? null)->remaining ?? 0); @endphp
                                <td class="px-4 py-3 text-center text-sm font-extrabold text-slate-700 dark:text-slate-200">{{ $fmt($colTotal) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <p class="text-xs text-slate-400">Showing {{ $employees->count() }} employee(s) across {{ $policies->count() }} categor{{ $policies->count() === 1 ? 'y' : 'ies' }}. Each cell is <b>remaining</b> of allocated days. Click a category to adjust individual balances.</p>
    @endif
</div>
@endsection
