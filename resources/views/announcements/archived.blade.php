@extends('layouts.hr-app')

@section('title', 'Archived Announcements')
@section('breadcrumb', 'Announcements · Archive')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="archive" class="h-6 w-6 text-slate-400"></i> Archived Announcements
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Deleted announcements are kept here. Restore one to show it again, or delete it permanently.</p>
        </div>
        <a href="{{ route('announcements.index') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Announcements
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif

    <div class="space-y-3">
        @forelse($announcements as $a)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($a->is_pinned)<span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-500/10"><i data-lucide="pin" class="h-3 w-3"></i> Pinned</span>@endif
                            @if($a->isExpired())<span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-600 dark:bg-rose-500/10">Expired</span>@endif
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $a->title }}</h3>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-1.5 whitespace-pre-line">{!! $a->bodyHtml() !!}</p>
                        <p class="text-[11px] text-slate-400 mt-2">
                            {{ optional($a->creator)->full_name ?? 'Admin' }} · posted {{ $a->created_at->format('d M Y') }}
                            @if($a->expires_at) · {{ $a->isExpired() ? 'expired' : 'expires' }} {{ $a->expires_at->format('d M Y') }}@endif
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5 inline-flex items-center gap-1">
                            <i data-lucide="archive" class="h-3 w-3"></i> Archived {{ $a->deleted_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <form method="POST" action="{{ route('announcements.restore', $a->id) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 hover:text-emerald-800"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Restore</button>
                    </form>
                    <form method="POST" action="{{ route('announcements.force-delete', $a->id) }}" onsubmit="return confirm('Permanently delete this announcement? This cannot be undone.')" class="ml-auto">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete permanently</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="archive" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">Archive is empty</p>
                <p class="text-xs text-slate-400 mt-1">Deleted announcements will appear here, ready to restore.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
