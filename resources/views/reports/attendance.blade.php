@extends('layouts.hr-app')

@section('title', 'Attendance Report')
@section('breadcrumb', 'Reports')

@php
    $fmt = function ($min) {
        $min = (int) $min;
        $sign = $min < 0 ? '-' : '';
        $a = abs($min);
        return $sign . intdiv($a, 60) . 'h ' . sprintf('%02d', $a % 60) . 'm';
    };
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Attendance</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Scheduled vs. worked hours, with deviations. {{ \Carbon\Carbon::parse($from)->format('d M') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
        </div>
        <a href="{{ route('reports.attendance', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 shrink-0">
            <i data-lucide="download" class="h-4 w-4"></i> Download CSV
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('reports.attendance') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Department</label>
            <select name="department" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="">All departments</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" @selected(($filters['department'] ?? '') == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Employee</label>
            <select name="employee" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="">All employees</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" @selected(($filters['employee'] ?? '') == $e->id)>{{ trim(($e->last_name ? $e->last_name.', ' : '').$e->first_name) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-900 dark:bg-slate-700">
            <i data-lucide="filter" class="h-4 w-4"></i> Apply
        </button>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3">ID</th>
                        <th class="px-5 py-3">Department</th>
                        <th class="px-5 py-3 text-right">Scheduled</th>
                        <th class="px-5 py-3 text-right">Worked</th>
                        <th class="px-5 py-3 text-right">Deviation</th>
                        <th class="px-5 py-3">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr class="border-b border-slate-100 last:border-0 dark:border-slate-700/60 {{ $r['reason'] ? 'bg-rose-50/60 dark:bg-rose-500/10' : '' }}">
                            <td class="px-5 py-2.5 whitespace-nowrap text-slate-600 dark:text-slate-300">{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                            <td class="px-5 py-2.5 font-semibold text-slate-800 dark:text-white whitespace-nowrap">{{ $r['name'] }}</td>
                            <td class="px-5 py-2.5 text-slate-500">{{ $r['employee_id'] }}</td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $r['department'] ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-right text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $fmt($r['scheduled']) }}</td>
                            <td class="px-5 py-2.5 text-right text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $fmt($r['worked']) }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold whitespace-nowrap {{ $r['deviation'] < 0 ? 'text-rose-600' : ($r['deviation'] > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                                {{ $r['deviation'] >= 0 ? '+' : '' }}{{ $fmt($r['deviation']) }}
                            </td>
                            <td class="px-5 py-2.5">
                                @if($r['reason'])<span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">{{ $r['reason'] }}</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-16 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="calendar-x" class="h-7 w-7"></i></div>
                            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No attendance data for this range</p>
                            <p class="text-xs text-slate-400 mt-1">Try a wider date range, or check that employees have work schedules and time entries.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-xs text-slate-400">{{ $rows->count() }} {{ Str::plural('row', $rows->count()) }} · scheduled from each employee's work schedule (or the default), worked from logged attendance.</p>
</div>
@endsection
