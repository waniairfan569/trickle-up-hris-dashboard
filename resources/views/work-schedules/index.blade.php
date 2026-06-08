@extends('layouts.hr-app')

@section('title', 'Work Schedules')
@section('breadcrumb', 'Work Schedules')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Work Schedules</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Define the working days and hours for your employees.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <a href="{{ route('work-schedules.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Schedule
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                <div class="ml-3">
                    <p class="text-sm font-medium text-emerald-800 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-400">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($schedules as $schedule)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700/80 relative overflow-hidden transition hover:shadow-md">
                @if($schedule->is_default)
                    <div class="absolute top-0 right-0 -mt-1 -mr-1">
                        <span class="inline-flex items-center rounded-bl-xl rounded-tr-xl bg-brand-100 px-3 py-1 text-xs font-bold text-brand-700 dark:bg-brand-500/20 dark:text-brand-400">
                            Default
                        </span>
                    </div>
                @endif
                
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $schedule->name }}</h3>
                        @if(!$schedule->is_active)
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700">Inactive</span>
                        @endif
                    </div>
                    
                    @if($schedule->description)
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4 line-clamp-2">{{ $schedule->description }}</p>
                    @endif

                    <!-- Working Days Badges -->
                    <div class="flex flex-wrap gap-1.5 mb-4 mt-4">
                        @php $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; @endphp
                        @foreach($days as $day)
                            @if(in_array($day, $schedule->working_days ?? []))
                                <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                    {{ $day }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-[10px] font-medium text-slate-400 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:ring-slate-700 dark:text-slate-500">
                                    {{ $day }}
                                </span>
                            @endif
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-6">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hours / Day</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ number_format($schedule->hours_per_day, 1) }}h</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Time</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($schedule->end_time)->format('H:i') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between dark:border-slate-700/60">
                    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <i data-lucide="users" class="h-4 w-4"></i>
                        <span class="font-medium">{{ $schedule->employees_count }} employees</span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        @if(!$schedule->is_default)
                            <form action="{{ route('work-schedules.set-default', $schedule) }}" method="POST">
                                @csrf
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-brand-600 transition rounded-lg hover:bg-brand-50 dark:hover:bg-slate-700 dark:hover:text-brand-400" title="Set as default">
                                    <i data-lucide="star" class="h-4 w-4"></i>
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('work-schedules.edit', $schedule) }}" class="p-1.5 text-slate-400 hover:text-brand-600 transition rounded-lg hover:bg-brand-50 dark:hover:bg-slate-700 dark:hover:text-brand-400">
                            <i data-lucide="pencil" class="h-4 w-4"></i>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                    <i data-lucide="clock" class="h-6 w-6 text-slate-400"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No schedules configured</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a new work schedule.</p>
                <div class="mt-6">
                    <a href="{{ route('work-schedules.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 transition">
                        <i data-lucide="plus" class="h-4 w-4"></i> Add Schedule
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
