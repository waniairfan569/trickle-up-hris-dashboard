@extends('layouts.hr-app')

@section('title', 'Company Forms')
@section('breadcrumb', 'Company Forms')

@php
    $badge = ['draft' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300', 'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400', 'closed' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400'];
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-5xl mx-auto space-y-6" x-data="{ tab: 'all', createOpen: false }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Company Forms</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Build dynamic forms, assign them to employees, and collect responses.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('company-forms.inbox') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="inbox" class="h-4 w-4"></i> Review responses</a>
            <button @click="createOpen = true" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700"><i data-lucide="plus" class="h-4 w-4"></i> Create new form</button>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
        @foreach(['all' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'closed' => 'Closed'] as $key => $label)
            <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">{{ $label }}</button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('company-forms.index') }}" class="flex items-center gap-1.5">
            <label for="cf-month" class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Created in</label>
            <input type="month" id="cf-month" name="month" value="{{ $month }}" onchange="this.form.submit()"
                   class="rounded-xl border border-slate-300 shadow-sm text-sm py-1.5 px-3 focus:border-brand-500 focus:ring-brand-500 dark:bg-slate-800 dark:border-slate-600 dark:text-white">
            @if($month)
                <a href="{{ route('company-forms.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-rose-600 dark:text-slate-400"><i data-lucide="x" class="h-3.5 w-3.5"></i> Clear</a>
            @endif
        </form>
        <a href="{{ route('company-forms.archived') }}" class="ml-auto inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
            <i data-lucide="archive" class="h-4 w-4"></i> Archive
        </a>
    </div>

    <div class="space-y-3">
        @forelse($forms as $form)
            @php $rate = $form->assigned_count > 0 ? round($form->submitted_count / $form->assigned_count * 100) : 0; @endphp
            <div x-show="tab === 'all' || tab === '{{ $form->status }}'" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('company-forms.builder', $form) }}" class="text-base font-bold text-slate-800 hover:text-brand-600 dark:text-white truncate">{{ $form->title }}</a>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge[$form->status] }}">{{ ucfirst($form->status) }}</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">{{ $form->fields_count }} fields · {{ $form->submitted_count }}/{{ $form->assigned_count }} submitted · created {{ $form->created_at->format('d M Y') }} @if($form->deadline) · due {{ $form->deadline->format('d M Y') }} @endif</p>
                        <div class="mt-2 h-1.5 w-48 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden"><div class="h-full bg-brand-500" style="width: {{ $rate }}%"></div></div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('company-forms.preview', $form) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="eye" class="h-3.5 w-3.5"></i> View</a>
                        <a href="{{ route('company-forms.builder', $form) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">Builder</a>
                        <a href="{{ route('company-forms.show', $form) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">Assign</a>
                        <a href="{{ route('company-forms.responses', $form) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">Responses</a>
                        <a href="{{ route('company-forms.export', $form) }}" title="Download responses (Excel)" class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="download" class="h-3.5 w-3.5"></i> Download</a>
                        <form action="{{ route('company-forms.destroy', $form) }}" method="POST" onsubmit="return confirm('Archive “{{ $form->title }}”? You can restore it later from the Archive.');">@csrf @method('DELETE')<button type="submit" title="Archive form" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="archive" class="h-4 w-4"></i></button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="clipboard-list" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No forms yet</p>
                <p class="text-xs text-slate-400 mt-1">Create your first form to get started.</p>
            </div>
        @endforelse
    </div>

    <!-- Create modal -->
    <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="createOpen = false"></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <form action="{{ route('company-forms.store') }}" method="POST">
                @csrf
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-lg font-extrabold text-slate-900 dark:text-white">New form</h2><button type="button" @click="createOpen = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button></div>
                <div class="p-6 space-y-4">
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title <span class="text-rose-500">*</span></label><input type="text" name="title" required maxlength="255" placeholder="e.g. Annual Feedback Survey" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label><textarea name="description" rows="2" maxlength="1000" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea></div>
                </div>
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60"><button type="button" @click="createOpen = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button><button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">Create &amp; build</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
