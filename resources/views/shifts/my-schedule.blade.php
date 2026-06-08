@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">My Schedule</h1>
            <p class="text-sm text-slate-500 mt-1">View your assigned work shifts.</p>
        </div>
    </div>

    @if($activeAssignment)
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col relative max-w-lg mx-auto">
        <div class="absolute left-0 top-0 bottom-0 w-2" style="background-color: {{ $activeAssignment->shift->color }}"></div>
        
        <div class="p-6 pl-8 flex-grow">
            <div class="flex justify-between items-start mb-2">
                <h3 class="text-xl font-bold text-slate-800">{{ $activeAssignment->shift->name }}</h3>
            </div>

            <div class="text-2xl font-light text-slate-700 mb-2">
                {{ substr($activeAssignment->shift->start_time, 0, 5) }} – {{ substr($activeAssignment->shift->end_time, 0, 5) }}
                @if($activeAssignment->shift->crosses_midnight)
                    <span class="text-sm text-slate-400 font-medium align-top">⁺¹</span>
                @endif
            </div>

            <div class="text-sm text-slate-500 mb-4 space-y-1">
                @php
                    $start = \Carbon\Carbon::parse($activeAssignment->shift->start_time);
                    $end = \Carbon\Carbon::parse($activeAssignment->shift->end_time);
                    if ($activeAssignment->shift->crosses_midnight) $end->addDay();
                    $durationHours = $start->diffInMinutes($end) / 60;
                @endphp
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

            <div class="flex flex-wrap gap-1 mt-auto">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                    @if(in_array($day, $activeAssignment->recurring_days ?? []))
                        <span class="bg-slate-100 text-slate-600 text-xs font-medium px-2 py-1 rounded">{{ $day }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center text-slate-500">
        <i data-lucide="calendar" class="w-12 h-12 mx-auto mb-4 text-slate-300"></i>
        <p>You have no active shift assigned.</p>
    </div>
    @endif
</div>
@endsection
