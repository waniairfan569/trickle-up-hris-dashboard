@extends('layouts.hr-app')

@section('title', 'Getting started')
@section('breadcrumb', 'Getting started')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="rocket" class="h-6 w-6 text-brand-500"></i> Welcome to {{ $tenant->displayName() }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">A few quick steps to get your workspace ready for your team.</p>
    </div>

    {{-- Progress --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $progress['done'] }} of {{ $progress['total'] }} done</p>
            <p class="text-sm font-extrabold {{ $progress['complete'] ? 'text-emerald-600' : 'text-brand-600 dark:text-brand-400' }}">{{ $progress['percent'] }}%</p>
        </div>
        <div class="h-2.5 w-full rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 {{ $progress['complete'] ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ $progress['percent'] }}%"></div>
        </div>
        @if($progress['complete'])
            <p class="mt-3 text-sm font-semibold text-emerald-600 flex items-center gap-1.5"><i data-lucide="party-popper" class="h-4 w-4"></i> You're all set — your workspace is ready.</p>
        @endif
    </div>

    {{-- Steps --}}
    <div class="space-y-3">
        @foreach($steps as $step)
            <div class="rounded-2xl border {{ $step['done'] ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-500/20 dark:bg-emerald-500/5' : 'border-slate-200/80 bg-white dark:border-slate-700 dark:bg-slate-800' }} shadow-sm p-5 flex items-center gap-4">
                <div class="shrink-0 grid place-items-center h-11 w-11 rounded-xl {{ $step['done'] ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' }}">
                    <i data-lucide="{{ $step['done'] ? 'check' : $step['icon'] }}" class="h-5 w-5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-slate-800 dark:text-white {{ $step['done'] ? 'line-through decoration-emerald-400/60' : '' }}">{{ $step['title'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $step['description'] }}</p>
                </div>
                @if($step['done'])
                    <span class="shrink-0 text-xs font-bold text-emerald-600 dark:text-emerald-400">Done</span>
                @else
                    <a href="{{ route($step['route']) }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-brand-700">
                        {{ $step['cta'] }} <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="text-center">
        <form method="POST" action="{{ route('getting-started.dismiss') }}">
            @csrf
            <button type="submit" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Hide this checklist</button>
        </form>
    </div>
</div>
<script>window.lucide && lucide.createIcons();</script>
@endsection
