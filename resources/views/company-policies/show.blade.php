@extends('layouts.hr-app')

@section('title', $policy->title)
@section('breadcrumb', 'Company Policies')

@php
    $statusBadge = ['draft' => 'bg-slate-100 text-slate-600', 'active' => 'bg-emerald-50 text-emerald-700', 'archived' => 'bg-rose-50 text-rose-700'];
@endphp

@section('content')
<style>[x-cloak]{display:none!important}
    .policy-content h1{font-size:1.5rem;font-weight:800;margin:1rem 0 .5rem}
    .policy-content h2{font-size:1.25rem;font-weight:700;margin:1rem 0 .5rem}
    .policy-content h3{font-size:1.1rem;font-weight:700;margin:.75rem 0 .5rem}
    .policy-content p{margin:.5rem 0;line-height:1.7}
    .policy-content ul{list-style:disc;padding-left:1.5rem;margin:.5rem 0}
    .policy-content ol{list-style:decimal;padding-left:1.5rem;margin:.5rem 0}
    .policy-content a{color:#2563eb;text-decoration:underline}
</style>

<div class="max-w-5xl mx-auto space-y-6" x-data="{ tab: 'content' }">
    <a href="{{ route('company-policies.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All policies</a>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <!-- Header -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $policy->title }}</h1>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusBadge[$policy->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($policy->status) }}</span>
                    <span class="text-[11px] font-semibold text-slate-400">v{{ $policy->version }}</span>
                </div>
                @if($policy->description)<p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">{{ $policy->description }}</p>@endif
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400 mt-3">
                    <span class="font-semibold text-slate-500">{{ $policy->category_label }}</span>
                    @if($policy->effective_date)<span>Effective {{ $policy->effective_date->format('d M Y') }}</span>@endif
                    @if($policy->review_date)<span>Review {{ $policy->review_date->format('d M Y') }}</span>@endif
                    @if($policy->requires_acknowledgment)<span class="inline-flex items-center gap-1"><i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i> Requires acknowledgment</span>@endif
                    @if($policy->requires_signature)<span class="inline-flex items-center gap-1"><i data-lucide="pen-line" class="h-3.5 w-3.5"></i> Requires signature</span>@endif
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('company-policies.edit', $policy) }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">Edit</a>
                <a href="{{ route('company-policies.acknowledgments', $policy) }}" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700">Tracking</a>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
        @foreach(['content' => 'Content', 'assign' => 'Assignments'] as $key => $label)
            <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">{{ $label }}</button>
        @endforeach
    </div>

    <!-- Content tab -->
    <div x-show="tab === 'content'" class="space-y-4">
        @if($policy->document_file)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700 flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 shrink-0"><i data-lucide="file-text" class="h-5 w-5"></i></div>
                    <div class="min-w-0"><p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $policy->document_filename }}</p><p class="text-xs text-slate-400">{{ $policy->document_size_label }}</p></div>
                </div>
                <a href="{{ route('policies.download', $policy) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="download" class="h-4 w-4"></i> Download</a>
            </div>
        @endif
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
            @if($policy->content)
                <div class="policy-content text-sm text-slate-700 dark:text-slate-200">{!! $policy->content !!}</div>
            @else
                <p class="text-sm text-slate-400 text-center py-8">No written content. {{ $policy->document_file ? 'See the attached document above.' : '' }}</p>
            @endif
        </div>
    </div>

    <!-- Assignments tab -->
    <div x-show="tab === 'assign'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Assign panel -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700 space-y-3"
             x-data="{ type: 'all' }">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white">Assign policy</h3>
            @if($errors->any())<div class="rounded-lg bg-rose-50 p-2.5 text-xs text-rose-700">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('company-policies.assign', $policy) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Assign to</label>
                    <select name="assigned_to_type" x-model="type" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="all">Everyone</option>
                        <option value="department">A department</option>
                        <option value="user">A specific employee</option>
                    </select>
                </div>
                <div x-show="type === 'department'" x-cloak>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Department</label>
                    <select name="assigned_to_id" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div x-show="type === 'user'" x-cloak>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Employee</label>
                    <select name="assigned_to_id" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Deadline <span class="text-slate-400 font-normal normal-case">(optional)</span></label>
                    <input type="date" name="deadline" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <button type="submit" class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">Assign</button>
            </form>
        </div>

        <!-- Current assignments -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-3">Current assignments</h3>
            @forelse($policy->assignments as $a)
                <div class="flex items-center justify-between py-2.5 border-b border-slate-100 dark:border-slate-700 last:border-0">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700"><i data-lucide="{{ $a->assigned_to_type === 'all' ? 'users' : ($a->assigned_to_type === 'department' ? 'building-2' : 'user') }}" class="h-4 w-4"></i></div>
                        <div><p class="text-sm font-semibold text-slate-800 dark:text-white">{{ $a->label }}</p>@if($a->deadline)<p class="text-xs text-slate-400">Due {{ $a->deadline->format('d M Y') }}</p>@endif</div>
                    </div>
                    <span class="text-xs text-slate-400">{{ optional($a->assigned_at)->diffForHumans() }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400 text-center py-8">Not assigned to anyone yet. Use the panel to assign this policy.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
