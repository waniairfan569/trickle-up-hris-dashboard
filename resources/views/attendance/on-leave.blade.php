@extends('layouts.hr-app')

@section('title', 'On Leave')
@section('breadcrumb', 'Attendance > On Leave')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="palmtree" class="h-6 w-6 text-blue-500"></i> On Leave
        </h1>
        <a href="{{ route('attendance.live') }}" class="rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Live Board</a>
    </div>

    <!-- On leave today -->
    @include('partials.on-leave-today', ['people' => $onLeavePeople])

    <!-- Upcoming approved leave (next 30 days) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i data-lucide="calendar-clock" class="h-4 w-4 text-slate-400"></i> Upcoming leave (next 30 days)
            </h2>
        </div>
        @forelse($upcoming as $r)
            @php $emp = $r->employee; @endphp
            <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                @if($emp->avatar_url)
                    <img src="{{ $emp->avatar_url }}" class="h-8 w-8 rounded-full object-cover" alt="">
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ $emp->initials }}</span>
                @endif
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800 dark:text-white truncate">{{ trim($emp->first_name . ' ' . $emp->last_name) }}</p>
                    <p class="text-xs text-slate-400">{{ optional($r->policy)->name ?: 'Leave' }}</p>
                </div>
                <span class="ml-auto text-xs text-slate-500 whitespace-nowrap">
                    {{ $r->start_date->format('d M') }} – {{ $r->end_date->format('d M Y') }}
                    <span class="text-slate-400">· {{ rtrim(rtrim(number_format($r->days_requested, 1), '0'), '.') }}d</span>
                </span>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-400">No upcoming approved leave in the next 30 days.</p>
        @endforelse
    </div>
</div>
@endsection
