@extends('layouts.hr-app')

@section('title', 'Onboarding Checklist')
@section('breadcrumb', 'Onboarding')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <a href="{{ route('onboarding.index') }}" class="text-brand-600 hover:underline">Onboarding</a> / 
                {{ $onboarding->employee->first_name }} {{ $onboarding->employee->last_name }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $onboarding->workflow->name }}
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-4">
            <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $onboarding->status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-400' }}">
                {{ str_replace('_', ' ', ucfirst($onboarding->status)) }}
            </span>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80">
        <div class="flex justify-between items-end mb-2">
            <div class="text-sm font-bold text-slate-900 dark:text-white">
                Overall Progress
            </div>
            <div class="text-2xl font-extrabold text-brand-600 dark:text-brand-400">
                {{ $onboarding->progressPercent() }}%
            </div>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3 dark:bg-slate-700">
            <div class="bg-brand-600 h-3 rounded-full transition-all duration-500" style="width: {{ $onboarding->progressPercent() }}%"></div>
        </div>
        <p class="text-xs text-slate-500 mt-2 text-right">
            {{ $onboarding->tasks->whereIn('status', ['completed', 'skipped'])->count() }} of {{ $onboarding->tasks->count() }} tasks completed
        </p>
    </div>

    <!-- Task List -->
    @php
        $myTasks = $onboarding->tasks->where('assigned_to_user_id', $user->id);
        $otherTasks = $onboarding->tasks->where('assigned_to_user_id', '!=', $user->id);
    @endphp

    <div class="space-y-8 mt-8">
        
        <!-- My Tasks -->
        @if($myTasks->isNotEmpty())
        <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-200 pb-2 dark:border-slate-700">Tasks Assigned to You</h3>
            <div class="space-y-3">
                @foreach($myTasks as $task)
                    <div class="bg-white rounded-xl shadow-sm border {{ $task->status === 'completed' ? 'border-emerald-200 bg-emerald-50/30 dark:border-emerald-500/20 dark:bg-emerald-500/5' : 'border-slate-200/80 dark:border-slate-700/80 dark:bg-slate-800' }} p-4 transition" x-data="{ showNotes: false }">
                        <div class="flex items-start gap-4">
                            @if($task->status === 'pending')
                                <form action="{{ route('onboarding.tasks.complete', $task) }}" method="POST" class="mt-1">
                                    @csrf
                                    <button type="submit" class="h-6 w-6 rounded border-2 border-slate-300 hover:border-brand-500 hover:bg-brand-50 flex items-center justify-center transition focus:outline-none dark:border-slate-600 dark:hover:border-brand-400 dark:hover:bg-brand-900/30 group">
                                        <i data-lucide="check" class="h-4 w-4 text-brand-600 opacity-0 group-hover:opacity-100 dark:text-brand-400"></i>
                                    </button>
                                </form>
                            @else
                                <div class="mt-1 h-6 w-6 rounded bg-emerald-500 text-white flex items-center justify-center">
                                    <i data-lucide="check" class="h-4 w-4"></i>
                                </div>
                            @endif
                            
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-slate-900 {{ $task->status !== 'pending' ? 'line-through text-slate-500' : '' }} dark:text-white dark:{{ $task->status !== 'pending' ? 'text-slate-500' : 'text-white' }}">{{ $task->title }}</h4>
                                @if($task->description)
                                    <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">{{ $task->description }}</p>
                                @endif
                                <div class="flex items-center gap-4 mt-2 text-[10px] uppercase font-bold tracking-wider">
                                    @php
                                        $isOverdue = $task->status === 'pending' && $task->due_date < \Carbon\Carbon::today();
                                    @endphp
                                    <span class="{{ $isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400' }}">
                                        <i data-lucide="calendar" class="h-3 w-3 inline mr-1 -mt-0.5"></i>
                                        Due {{ $task->due_date->format('M d, Y') }}
                                    </span>
                                    
                                    @if($task->status === 'pending')
                                        <button @click="showNotes = !showNotes" class="text-brand-600 hover:text-brand-800 dark:text-brand-400">Add Note</button>
                                    @endif
                                </div>
                                
                                @if($task->status === 'pending')
                                <div x-show="showNotes" style="display: none;" class="mt-3">
                                    <form action="{{ route('onboarding.tasks.complete', $task) }}" method="POST" class="flex gap-2">
                                        @csrf
                                        <input type="text" name="notes" placeholder="Optional notes before completing..." class="flex-1 rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                        <button type="submit" class="px-3 py-1.5 bg-brand-600 text-slate-900 rounded-lg text-xs font-bold hover:bg-brand-700">Complete with Note</button>
                                    </form>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Other Tasks -->
        @if($otherTasks->isNotEmpty())
        <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-200 pb-2 dark:border-slate-700">Other Required Tasks</h3>
            <div class="space-y-3 opacity-75">
                @foreach($otherTasks as $task)
                    <div class="bg-slate-50 rounded-xl shadow-sm border border-slate-200/80 p-4 dark:bg-slate-900/50 dark:border-slate-700/60">
                        <div class="flex items-start gap-4">
                            <div class="mt-1 h-6 w-6 rounded border-2 border-slate-300 bg-slate-100 flex items-center justify-center dark:border-slate-600 dark:bg-slate-800">
                                @if($task->status !== 'pending')
                                    <i data-lucide="check" class="h-4 w-4 text-slate-400"></i>
                                @endif
                            </div>
                            
                            <div class="flex-1 flex justify-between items-center">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-700 {{ $task->status !== 'pending' ? 'line-through text-slate-400' : '' }} dark:text-slate-300 dark:{{ $task->status !== 'pending' ? 'text-slate-500' : 'text-slate-300' }}">{{ $task->title }}</h4>
                                    <div class="text-[10px] text-slate-500 mt-1 uppercase font-bold tracking-wider flex gap-4">
                                        <span>Assigned to: {{ $task->assignedTo->first_name }} {{ $task->assignedTo->last_name }}</span>
                                        <span class="{{ $task->status === 'pending' && $task->due_date < \Carbon\Carbon::today() ? 'text-rose-600 dark:text-rose-400' : '' }}">Due: {{ $task->due_date->format('M d') }}</span>
                                    </div>
                                </div>
                                
                                @if($task->status === 'pending' && (auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin')))
                                    <form action="{{ route('onboarding.tasks.skip', $task) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-xs text-slate-500 hover:text-rose-600 font-bold dark:text-slate-400 dark:hover:text-rose-400" onclick="return confirm('Are you sure you want to manually skip this task?');">Skip</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>
@endsection
