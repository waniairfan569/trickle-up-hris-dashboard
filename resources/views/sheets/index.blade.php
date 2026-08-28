@extends('layouts.hr-app')

@section('title', 'Sheets')
@section('breadcrumb', 'Sheets')

@php
    $providerIcon = ['google' => 'sheet', 'excel' => 'table-2', 'airtable' => 'grid-3x3', 'link' => 'link'];
    $providerTone = ['google' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400',
                     'excel' => 'text-teal-600 bg-teal-50 dark:bg-teal-500/10 dark:text-teal-400',
                     'airtable' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400',
                     'link' => 'text-slate-500 bg-slate-100 dark:bg-slate-700 dark:text-slate-300'];
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="sheetsPage()">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="sheet" class="h-6 w-6 text-brand-500"></i> Sheets
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Quick links to your Google Sheets &amp; spreadsheets — open or preview them right here.</p>
        </div>
        <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-400 transition">
            <i data-lucide="plus" class="h-4 w-4"></i> Add sheet
        </button>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('sheets.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[11px] font-bold text-slate-500 mb-1">Search</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Name, description or category…" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">Category</label>
            <select name="category" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected($category===$cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
            <i data-lucide="filter" class="h-4 w-4"></i> Filter
        </button>
        @if($q || $category)
            <a href="{{ route('sheets.index') }}" class="text-sm text-slate-400 hover:text-slate-600 underline">Clear</a>
        @endif
    </form>

    {{-- Groups --}}
    @forelse($grouped as $groupName => $sheets)
        <div>
            <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 px-1 flex items-center gap-2">
                <i data-lucide="folder" class="h-3.5 w-3.5"></i> {{ $groupName }}
                <span class="text-slate-300 dark:text-slate-600">·</span>
                <span class="font-semibold text-slate-400">{{ $sheets->count() }}</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($sheets as $s)
                    <div class="group flex flex-col rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-md transition dark:bg-slate-800 dark:border-slate-700">
                        <div class="flex items-start gap-3">
                            <span class="grid place-items-center h-10 w-10 shrink-0 rounded-xl {{ $providerTone[$s->provider] ?? $providerTone['link'] }}">
                                <i data-lucide="{{ $providerIcon[$s->provider] ?? 'link' }}" class="h-5 w-5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-800 dark:text-white truncate">{{ $s->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $s->provider_label }}@if($s->opens_count) · {{ $s->opens_count }} open{{ $s->opens_count === 1 ? '' : 's' }}@endif</p>
                            </div>
                        </div>

                        @if($s->description)
                            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400 line-clamp-2">{{ $s->description }}</p>
                        @endif

                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-1.5">
                            <a href="{{ route('sheets.open', $s) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-bold text-slate-900 hover:bg-brand-400">
                                <i data-lucide="external-link" class="h-3.5 w-3.5"></i> Open
                            </a>
                            <a href="{{ route('sheets.preview', $s) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i> Preview
                            </a>
                            <div class="ml-auto flex items-center gap-1">
                                <button type="button" title="Edit"
                                        @click="openEdit({{ Illuminate\Support\Js::from([
                                            'id' => $s->id, 'name' => $s->name, 'url' => $s->url,
                                            'description' => $s->description, 'category' => $s->category,
                                        ]) }})"
                                        class="inline-grid place-items-center h-7 w-7 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-brand-600 dark:hover:bg-slate-700">
                                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                </button>
                                <form method="POST" action="{{ route('sheets.destroy', $s) }}" onsubmit="return confirm('Remove “{{ $s->name }}” from the library?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Remove"
                                            class="inline-grid place-items-center h-7 w-7 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
            <i data-lucide="sheet" class="h-10 w-10 text-slate-300 dark:text-slate-600 mx-auto"></i>
            <p class="mt-3 font-bold text-slate-600 dark:text-slate-300">No sheets yet</p>
            <p class="text-sm text-slate-400 mt-1">Add a Google Sheet link and it will show up here for quick access.</p>
            <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 mt-4 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-400">
                <i data-lucide="plus" class="h-4 w-4"></i> Add your first sheet
            </button>
        </div>
    @endforelse

    {{-- Add / edit modal --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4 overflow-y-auto" @keydown.escape.window="open=false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800 my-8" @click.away="open=false">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="sheet" class="h-5 w-5 text-brand-500"></i> <span x-text="isEdit ? 'Edit sheet' : 'Add a sheet'"></span>
            </h3>
            <form :action="formAction" method="POST" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Name</label>
                    <input type="text" name="name" x-model="form.name" maxlength="150" required placeholder="e.g. Payroll Master 2026"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Google Sheet / spreadsheet link</label>
                    <input type="url" name="url" x-model="form.url" required placeholder="https://docs.google.com/spreadsheets/d/…"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <p class="text-[11px] text-slate-400 mt-1">Tip: for the in-app preview to work, share the sheet as “Anyone with the link · Viewer”.</p>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Category <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" name="category" x-model="form.category" maxlength="80" list="sheet-categories" placeholder="Finance, HR…"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <datalist id="sheet-categories">
                        @foreach($categories as $cat)<option value="{{ $cat }}">@endforeach
                    </datalist>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Description <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea name="description" x-model="form.description" rows="2" maxlength="1000" placeholder="What is this sheet for?"
                              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" @click="open=false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-brand-500 px-5 py-2 text-sm font-bold text-slate-900 hover:bg-brand-400" x-text="isEdit ? 'Save changes' : 'Add sheet'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function sheetsPage() {
        const blank = { name: '', url: '', description: '', category: '' };
        return {
            open: false,
            isEdit: false,
            storeUrl: '{{ route('sheets.store') }}',
            form: { ...blank },
            formAction: '{{ route('sheets.store') }}',
            openCreate() {
                this.isEdit = false;
                this.form = { ...blank };
                this.formAction = this.storeUrl;
                this.open = true;
            },
            openEdit(sheet) {
                this.isEdit = true;
                this.form = {
                    name: sheet.name || '',
                    url: sheet.url || '',
                    description: sheet.description || '',
                    category: sheet.category || '',
                };
                this.formAction = '{{ url('sheets') }}/' + sheet.id;
                this.open = true;
            },
        };
    }
</script>
@endsection
