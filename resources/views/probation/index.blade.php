@extends('layouts.hr-app')

@section('title', 'Probation')
@section('breadcrumb', 'Probation')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="clipboard-check" class="h-6 w-6 text-brand-500"></i> Probation
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Employees currently serving a probation period.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-xl bg-brand-50 px-4 py-2 text-sm font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                {{ $probations->count() }} on probation
            </span>
            @if($overdue > 0)
                <span class="inline-flex items-center gap-2 rounded-xl bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                    <i data-lucide="alert-triangle" class="h-4 w-4"></i> {{ $overdue }} review overdue
                </span>
            @endif
        </div>
    </div>

    @if($probations->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="clipboard-check" class="h-7 w-7"></i></div>
            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No one is on probation</p>
            <p class="text-xs text-slate-400 mt-1">Start a probation from an employee's Job tab.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Start</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Ends</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Remaining</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200 dark:bg-slate-800 dark:divide-slate-700">
                    @foreach($probations as $p)
                        @php
                            $emp = $p->employee;
                            $days = $p->days_remaining;
                            $isOverdue = $p->end_date->lt(now()->startOfDay());
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <a href="{{ route('employees.profile', $emp->id) }}" class="flex items-center gap-3 group">
                                    @if($emp->avatar_url)
                                        <img src="{{ $emp->avatar_url }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                    @else
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-indigo-500 text-xs font-bold text-white">{{ $emp->initials }}</span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-slate-900 group-hover:text-brand-600 dark:text-white truncate">{{ $emp->full_name }}</span>
                                        <span class="block text-[11px] text-slate-400 truncate">{{ $emp->job_title ?: '—' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">{{ optional($emp->department)->name ?? '—' }}</td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $p->start_date->format('d M Y') }}</td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                {{ $p->end_date->format('d M Y') }}
                                @if($p->is_extended)<span class="ml-1 inline-flex items-center rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">extended</span>@endif
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-sm font-semibold {{ $isOverdue ? 'text-rose-600' : ($days <= 7 ? 'text-amber-600' : 'text-slate-600 dark:text-slate-300') }}">
                                {{ $isOverdue ? abs($days) . ' day(s) overdue' : $days . ' day(s)' }}
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-bold {{ $isOverdue ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' }}">
                                    {{ $p->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-right">
                                <a href="{{ route('employees.profile', $emp->id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700">Review <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
