@extends('layouts.hr-app')

@section('title', 'Deleted HR Documents')
@section('breadcrumb', 'HR Documents · Deleted')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2"><i data-lucide="trash-2" class="h-6 w-6 text-slate-400"></i> Deleted Documents</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Deleted HR documents are kept here. Restore one, or remove it permanently.</p>
        </div>
        <a href="{{ route('hr-documents.index') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Documents</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="space-y-3">
        @forelse($documents as $doc)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-base font-bold text-slate-800 dark:text-white truncate">{{ $doc->title ?: $doc->template_name }}</span>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ ucfirst($doc->status) }}</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ optional($doc->employee)->full_name ?? '—' }}
                            @if($doc->period_start) · {{ $doc->period_start->format('M Y') }}@endif
                            · created {{ $doc->created_at->format('d M Y') }}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5 inline-flex items-center gap-1"><i data-lucide="trash-2" class="h-3 w-3"></i> Deleted {{ $doc->deleted_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <form action="{{ route('hr-documents.restore', $doc->id) }}" method="POST">@csrf
                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Restore</button>
                        </form>
                        <form action="{{ route('hr-documents.force-delete', $doc->id) }}" method="POST" onsubmit="return confirm('Permanently delete this document? This cannot be undone.');">@csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete permanently</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="trash-2" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">Nothing deleted</p>
                <p class="text-xs text-slate-400 mt-1">Deleted documents will appear here, ready to restore.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
