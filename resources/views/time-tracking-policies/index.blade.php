@extends('layouts.hr-app')

@section('title', 'Time Tracking')
@section('breadcrumb', 'Time Tracking')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{ search: @js($search) }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Time Tracking</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Policies that define how employees log their work hours.</p>
        </div>
        <a href="{{ route('time-tracking-policies.create') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition shrink-0">
            <i data-lucide="plus" class="h-4 w-4"></i> Add time tracking policy
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i><span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="relative">
        <i data-lucide="search" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" x-model="search" placeholder="Search policies…"
               class="w-full rounded-xl border-slate-300 pl-10 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        @forelse($policies as $policy)
            @php
                $deptCount = $policy->departments->count();
                $entCount = $policy->entities->count();
                $scope = ($deptCount === 0 && $entCount === 0)
                    ? 'All employees'
                    : trim(($deptCount ? "Applied in {$deptCount} " . Str::plural('department', $deptCount) : 'All departments')
                        . ' · ' . ($entCount ? "{$entCount} " . Str::plural('location', $entCount) : 'all locations'));
            @endphp
            <div data-name="{{ strtolower($policy->name) }}"
                 x-show="search === '' || $el.dataset.name.includes(search.toLowerCase())"
                 class="flex items-center gap-4 px-6 py-4 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10">
                    <i data-lucide="timer" class="h-5 w-5"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('time-tracking-policies.edit', $policy) }}" class="text-sm font-bold text-slate-800 hover:text-brand-600 dark:text-white truncate block">{{ $policy->name }}</a>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                        Modified on {{ $policy->updated_at->format('d M Y') }} <span class="text-slate-300 dark:text-slate-600">|</span> {{ $scope }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                    <i data-lucide="users" class="h-3.5 w-3.5"></i> {{ $policy->employee_count }}
                </span>
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                        <i data-lucide="more-vertical" class="h-5 w-5"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 z-20 mt-1 w-40 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                        <a href="{{ route('time-tracking-policies.edit', $policy) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                            <i data-lucide="pencil" class="h-4 w-4 text-slate-400"></i> Edit
                        </a>
                        <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                        <form action="{{ route('time-tracking-policies.destroy', $policy) }}" method="POST" onsubmit="return confirm('Delete “{{ $policy->name }}”?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="timer" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No time tracking policies yet</p>
                <p class="text-xs text-slate-400 mt-1">Create a policy to define how employees log their hours.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
