@extends('layouts.hr-app')

@section('title', 'Team Calendar')
@section('breadcrumb', 'Team Calendar')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <a href="{{ route('time-off.index') }}" class="text-brand-600 hover:underline">Time-Off</a> / 
                Team Calendar
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                View upcoming team availability and approved leave.
            </p>
        </div>
    </div>

    <!-- Simple Calendar Render (List representation for brevity, expandable to full grid using JS/FullCalendar in future) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Upcoming Approved Leaves (Next 30 Days)</h3>
        
        <div class="space-y-4">
            @php
                $upcoming = $requests->filter(function($r) {
                    return $r->status === 'approved' && $r->end_date >= \Carbon\Carbon::today() && $r->start_date <= \Carbon\Carbon::today()->addDays(30);
                })->sortBy('start_date');
            @endphp
            
            @forelse($upcoming as $req)
                <div class="flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-700/60">
                    <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold dark:bg-brand-900 dark:text-brand-300 shrink-0">
                        {{ substr($req->employee->first_name, 0, 1) }}{{ substr($req->employee->last_name, 0, 1) }}
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $req->employee->first_name }} {{ $req->employee->last_name }}</h4>
                        <div class="flex items-center text-xs text-slate-500 mt-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                            {{ $req->policy->name }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-slate-900 dark:text-white">
                            {{ $req->start_date->format('M d') }} 
                            @if($req->start_date != $req->end_date)
                                - {{ $req->end_date->format('M d') }}
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 mt-1">{{ (float) $req->days_requested }} days</div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-slate-500">No approved team leaves in the upcoming 30 days.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
