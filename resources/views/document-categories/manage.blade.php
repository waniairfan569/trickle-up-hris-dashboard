@extends('layouts.hr-app')

@section('title', 'Document Categories')
@section('breadcrumb', 'Company Documents · Categories')

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2"><i data-lucide="folders" class="h-6 w-6 text-brand-500"></i> Document Categories</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Organise the document library. Rename or remove categories here.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('company-documents.admin') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-4 w-4"></i> Documents</a>
            <a href="{{ route('document-categories.deleted') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="archive" class="h-4 w-4"></i> Archive @if($deletedCount)({{ $deletedCount }})@endif</a>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Add category --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-3">New category</h2>
        <form method="POST" action="{{ route('document-categories.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[180px]"><label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Name</label><input type="text" name="name" required maxlength="120" placeholder="e.g. Contracts" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            <div class="w-28"><label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Icon</label><input type="text" name="icon" maxlength="60" placeholder="folder" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            <div class="w-24"><label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Color</label><input type="text" name="color" maxlength="20" placeholder="blue" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            <button type="submit" class="btn-brand"><i data-lucide="plus" class="h-4 w-4"></i> Add</button>
        </form>
    </div>

    {{-- List --}}
    <div class="space-y-2">
        @forelse($categories as $cat)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700" x-data="{ edit: false }">
                <div x-show="!edit" class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-700"><i data-lucide="{{ $cat->icon ?: 'folder' }}" class="h-4 w-4"></i></div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $cat->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ $cat->documents_count }} document(s) @if($cat->color) · {{ $cat->color }} @endif</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="edit = true" class="rounded-lg p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                        <form method="POST" action="{{ route('document-categories.destroy', $cat) }}" onsubmit="return confirm('Archive “{{ $cat->name }}”? Its {{ $cat->documents_count }} document(s) become uncategorized. You can restore it later.');">@csrf @method('DELETE')
                            <button type="submit" class="rounded-lg p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Archive"><i data-lucide="archive" class="h-4 w-4"></i></button>
                        </form>
                    </div>
                </div>
                <form x-show="edit" x-cloak method="POST" action="{{ route('document-categories.update', $cat) }}" class="flex flex-wrap items-end gap-3">
                    @csrf @method('PUT')
                    <div class="flex-1 min-w-[180px]"><label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Name</label><input type="text" name="name" value="{{ $cat->name }}" required maxlength="120" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                    <div class="w-28"><label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Icon</label><input type="text" name="icon" value="{{ $cat->icon }}" maxlength="60" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                    <div class="w-24"><label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Color</label><input type="text" name="color" value="{{ $cat->color }}" maxlength="20" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                    <div class="flex gap-2"><button type="button" @click="edit = false" class="btn-outline">Cancel</button><button type="submit" class="btn-brand">Save</button></div>
                </form>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="folders" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No categories yet</p>
                <p class="text-xs text-slate-400 mt-1">Add one above to organise your documents.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
