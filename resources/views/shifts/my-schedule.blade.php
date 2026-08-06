@extends('layouts.hr-app')

@section('title', 'My Schedule')
@section('breadcrumb', 'My Schedule')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="border-b border-slate-200/80 dark:border-slate-700/60 pb-5">
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">My Schedule</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">View your assigned work shifts.</p>
    </div>

    @if($activeAssignment)
    @php
        $start = \Carbon\Carbon::parse($activeAssignment->shift->start_time);
        $end = \Carbon\Carbon::parse($activeAssignment->shift->end_time);
        if ($activeAssignment->shift->crosses_midnight) $end->addDay();
        $durationHours = $start->diffInMinutes($end) / 60;
        $shiftColor = $activeAssignment->shift->color ?: '#eab308';
    @endphp
    <div class="relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 max-w-xl">
        <div class="absolute left-0 top-0 bottom-0 w-1.5" style="background-color: {{ $shiftColor }}"></div>

        <div class="p-6 pl-8">
            <div class="flex items-center gap-2.5 mb-3">
                <span class="h-9 w-9 grid place-items-center rounded-xl" style="background-color: {{ $shiftColor }}1a; color: {{ $shiftColor }}">
                    <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">{{ $activeAssignment->shift->name }}</h3>
            </div>

            <div class="text-3xl font-light text-slate-800 dark:text-white mb-3 tracking-tight">
                {{ substr($activeAssignment->shift->start_time, 0, 5) }} – {{ substr($activeAssignment->shift->end_time, 0, 5) }}
                @if($activeAssignment->shift->crosses_midnight)
                    <span class="text-sm text-slate-400 font-medium align-top">⁺¹</span>
                @endif
            </div>

            <div class="text-sm text-slate-500 dark:text-slate-400 space-y-1.5 mb-5">
                <p class="flex items-center">
                    <i data-lucide="clock" class="w-4 h-4 mr-2 text-slate-400"></i>
                    {{ rtrim(rtrim(number_format($durationHours, 1), '0'), '.') }} hours
                    (includes {{ $activeAssignment->shift->break_minutes }} min break)
                </p>
                <p class="flex items-center">
                    <i data-lucide="calendar" class="w-4 h-4 mr-2 text-slate-400"></i>
                    Assigned on {{ \Carbon\Carbon::parse($activeAssignment->recurring_start_date)->format('M d, Y') }}
                </p>
            </div>

            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-2">Working days</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                        @php $on = in_array($day, $activeAssignment->recurring_days ?? []); @endphp
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-lg {{ $on
                            ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                            : 'bg-slate-50 text-slate-300 dark:bg-slate-900/40 dark:text-slate-600 line-through' }}">{{ $day }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center text-slate-500 dark:text-slate-400 max-w-xl">
        <i data-lucide="calendar" class="w-12 h-12 mx-auto mb-4 text-slate-300"></i>
        <p class="font-semibold text-slate-600 dark:text-slate-300">You have no active shift assigned.</p>
        <p class="text-sm mt-1">Your manager will assign a work schedule soon.</p>
    </div>
    @endif
</div>
@endsection
