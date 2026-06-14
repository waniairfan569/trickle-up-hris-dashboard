@extends('layouts.hr-app')

@section('title', 'Work Calendar')
@section('breadcrumb', 'Work Calendar')

@section('content')
@php
    $prevWeek = $start->copy()->subWeek()->toDateString();
    $nextWeek = $start->copy()->addWeek()->toDateString();
    $today = \Carbon\Carbon::today()->toDateString();
@endphp
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Work Calendar</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Everyone's working hours for the week.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <!-- Department filter -->
            <form method="GET" action="{{ route('work-calendar') }}" class="flex items-center gap-2">
                <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                <select name="department" onchange="this.form.submit()"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold bg-white focus:border-brand-500 dark:bg-slate-800 dark:border-slate-700 dark:text-white">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </form>
            <!-- Week nav -->
            <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2 py-1 dark:bg-slate-800 dark:border-slate-700">
                <a href="{{ route('work-calendar', ['week' => $prevWeek, 'department' => request('department')]) }}" class="h-7 w-7 grid place-items-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="chevron-left" class="h-4 w-4"></i></a>
                <span class="text-xs font-bold text-slate-600 dark:text-slate-300 px-2 whitespace-nowrap">{{ $start->format('d M') }} – {{ $start->copy()->addDays(6)->format('d M Y') }}</span>
                <a href="{{ route('work-calendar', ['week' => $nextWeek, 'department' => request('department')]) }}" class="h-7 w-7 grid place-items-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="chevron-right" class="h-4 w-4"></i></a>
            </div>
            <a href="{{ route('work-calendar') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">This week</a>
        </div>
    </div>

    <!-- Grid -->
    <div class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="bg-slate-50/75 border-b border-slate-100 dark:bg-slate-900/40 dark:border-slate-700/60">
                    <th class="sticky left-0 z-10 bg-slate-50/95 dark:bg-slate-900/80 py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-450 min-w-[200px]">Employee</th>
                    @foreach($days as $day)
                        <th class="py-3 px-3 text-center min-w-[110px] {{ $day->toDateString() === $today ? 'bg-brand-50/60 dark:bg-brand-500/10' : '' }}">
                            <div class="text-[10px] font-bold uppercase tracking-wider {{ $day->toDateString() === $today ? 'text-brand-600' : 'text-slate-450' }}">{{ $day->format('D') }}</div>
                            <div class="text-xs font-bold {{ $day->toDateString() === $today ? 'text-brand-600' : 'text-slate-700 dark:text-slate-300' }}">{{ $day->format('j M') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-700/60">
                @forelse($users as $u)
                    <tr class="hover:bg-slate-50/40 dark:hover:bg-slate-750/30">
                        <td class="sticky left-0 z-10 bg-white dark:bg-slate-800 py-3 px-4 border-r border-slate-100 dark:border-slate-700/60">
                            <div class="flex items-center gap-3">
                                @if($u->avatar_url)
                                    <img src="{{ $u->avatar_url }}" alt="{{ $u->full_name }}" class="h-8 w-8 rounded-lg object-cover">
                                @else
                                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-brand-400 to-indigo-500 grid place-items-center text-white text-[10px] font-bold">{{ $u->initials }}</div>
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('employees.profile', $u->id) }}" class="text-sm font-bold text-slate-800 dark:text-white hover:text-brand-600 truncate block">{{ $u->full_name }}</a>
                                    <div class="text-[10px] text-slate-400 truncate">{{ $u->job_title ?: 'Employee' }}</div>
                                </div>
                            </div>
                        </td>
                        @foreach($days as $day)
                            @php $shift = $schedule[$u->id][$day->toDateString()] ?? null; @endphp
                            <td class="py-3 px-2 text-center align-middle {{ $day->toDateString() === $today ? 'bg-brand-50/40 dark:bg-brand-500/5' : '' }}">
                                @if($shift)
                                    <div class="inline-block rounded-lg px-2 py-1 text-[11px] font-bold leading-tight" style="background-color: {{ $shift->color }}1a; color: {{ $shift->color }};">
                                        {{ \Carbon\Carbon::parse($shift->start_time)->format('g:i A') }}<br>{{ \Carbon\Carbon::parse($shift->end_time)->format('g:i A') }}
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-300 dark:text-slate-600 font-semibold">Off</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-slate-400 italic">No employees to display.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-[11px] text-slate-400">Times shown are each employee's assigned shift. "Off" means no shift is scheduled that day.</p>
</div>
@endsection
