@extends('layouts.hr-app')

@section('title', 'My Schedule')
@section('breadcrumb', 'My Schedule')

@section('content')
<div class="space-y-6">
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

    @if(session('success'))
        <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 max-w-xl dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 max-w-xl dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-400">
            <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Shifts this person has said they will cover. Shown before the open
         ones, because what you have agreed to matters more than what is going. --}}
    @if(!empty($covering) && $covering->isNotEmpty())
    <div class="max-w-xl">
        <h2 class="mb-2 text-sm font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">You are covering</h2>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
            @foreach($covering as $shiftCover)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4 last:border-b-0 dark:border-slate-700/60">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-block h-9 w-1.5 shrink-0 rounded-full" style="background-color: {{ $shiftCover->shift->color ?? '#3B82F6' }}"></span>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-white">
                                {{ $shiftCover->date->format('D j M') }} · {{ $shiftCover->shift->name }}
                            </p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ substr($shiftCover->shift->start_time, 0, 5) }}–{{ substr($shiftCover->shift->end_time, 0, 5) }}
                                @if($shiftCover->coveringFor)
                                    · for {{ $shiftCover->coveringFor->first_name }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($shiftCover->date->gt(today()))
                        <form method="POST" action="{{ route('shifts.release', $shiftCover) }}"
                              onsubmit="return confirm('Give this shift back? It goes back to having nobody on it.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-500 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                Give back
                            </button>
                        </form>
                    @else
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-400">Today</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Holes anybody could fill. Offered here as well as at the clock-in screen,
         because somebody who clocks in on the device is never asked anything. --}}
    @if(!empty($openShifts) && $openShifts->isNotEmpty())
    <div class="max-w-xl">
        <h2 class="mb-2 text-sm font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">Shifts going spare</h2>
        <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
            Days you have nothing on, where somebody else is away. Taking one changes that day only.
        </p>
        <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white dark:border-amber-500/20 dark:bg-slate-800">
            @foreach($openShifts as $spare)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4 last:border-b-0 dark:border-slate-700/60">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-block h-9 w-1.5 shrink-0 rounded-full" style="background-color: {{ $spare->shift->color ?? '#3B82F6' }}"></span>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-white">
                                {{ $spare->date->format('D j M') }} · {{ $spare->shift->name }}
                            </p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ substr($spare->shift->start_time, 0, 5) }}–{{ substr($spare->shift->end_time, 0, 5) }}
                                · {{ $spare->user->first_name }} is away
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('shifts.claim') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $spare->date->toDateString() }}">
                        <input type="hidden" name="shift_id" value="{{ $spare->shift->id }}">
                        <input type="hidden" name="covers_user_id" value="{{ $spare->user->id }}">
                        <button type="submit" class="shrink-0 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 shadow-sm transition hover:bg-brand-700">
                            I'll take it
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
