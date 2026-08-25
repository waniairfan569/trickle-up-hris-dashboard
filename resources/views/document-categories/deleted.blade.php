@extends('layouts.hr-app')

@section('title', 'Archived Categories')
@section('breadcrumb', 'Document Categories · Archive')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2"><i data-lucide="archive" class="h-6 w-6 text-slate-400"></i> Archived Categories</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Restore a category to use it again, or delete it permanently.</p>
        </div>
        <a href="{{ route('document-categories.manage') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Categories</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="space-y-2">
        @forelse($categories as $cat)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-700"><i data-lucide="{{ $cat->icon ?: 'folder' }}" class="h-4 w-4"></i></div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $cat->name }}</p>
                        <p class="text-[11px] text-slate-400 inline-flex items-center gap-1"><i data-lucide="archive" class="h-3 w-3"></i> Archived {{ $cat->deleted_at->diffForHumans() }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <form method="POST" action="{{ route('document-categories.restore', $cat->id) }}">@csrf
                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Restore</button>
                    </form>
                    <form method="POST" action="{{ route('document-categories.force-delete', $cat->id) }}" onsubmit="return confirm('Permanently delete “{{ $cat->name }}”? This cannot be undone.');">@csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete permanently</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="archive" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">Archive is empty</p>
                <p class="text-xs text-slate-400 mt-1">Deleted categories will appear here, ready to restore.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
