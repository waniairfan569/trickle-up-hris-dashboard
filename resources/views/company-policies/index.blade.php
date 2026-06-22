@extends('layouts.hr-app')

@section('title', 'Company Policies')
@section('breadcrumb', 'Company Policies')

@php
    $statusBadge = ['draft' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300', 'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400', 'archived' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400'];
    $catBadge = ['hr' => 'bg-indigo-50 text-indigo-700', 'it' => 'bg-sky-50 text-sky-700', 'finance' => 'bg-emerald-50 text-emerald-700', 'legal' => 'bg-amber-50 text-amber-700', 'health_safety' => 'bg-rose-50 text-rose-700', 'general' => 'bg-slate-100 text-slate-600'];
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{ tab: 'all' }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Company Policies</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Publish policies, assign to employees, and track acknowledgments.</p>
        </div>
        <a href="{{ route('company-policies.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700"><i data-lucide="plus" class="h-4 w-4"></i> New policy</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
        @foreach(['all' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $key => $label)
            <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">{{ $label }}</button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse($policies as $policy)
            <div x-show="tab === 'all' || tab === '{{ $policy->status }}'" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('company-policies.show', $policy) }}" class="text-base font-bold text-slate-800 hover:text-brand-600 dark:text-white truncate">{{ $policy->title }}</a>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $catBadge[$policy->category] ?? 'bg-slate-100 text-slate-600' }} dark:bg-slate-700 dark:text-slate-300">{{ $policy->category_label }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusBadge[$policy->status] }}">{{ ucfirst($policy->status) }}</span>
                            <span class="text-[11px] font-semibold text-slate-400">v{{ $policy->version }}</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">{{ $policy->acknowledgments_count }} acknowledgment(s) · {{ $policy->acknowledgment_rate }}% complete @if($policy->effective_date) · effective {{ $policy->effective_date->format('d M Y') }} @endif</p>
                        <div class="mt-2 h-1.5 w-48 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden"><div class="h-full bg-brand-500" style="width: {{ $policy->acknowledgment_rate }}%"></div></div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('company-policies.show', $policy) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">View</a>
                        <a href="{{ route('company-policies.edit', $policy) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">Edit</a>
                        <a href="{{ route('company-policies.acknowledgments', $policy) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">Tracking</a>
                        <form action="{{ route('company-policies.destroy', $policy) }}" method="POST" onsubmit="return confirm('Delete “{{ $policy->title }}”?');">@csrf @method('DELETE')<button class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="book-text" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No policies yet</p>
                <p class="text-xs text-slate-400 mt-1">Create your first company policy.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
