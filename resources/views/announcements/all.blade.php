@extends('layouts.hr-app')

@section('title', 'Announcements')
@section('breadcrumb', 'Announcements')

@section('content')
<div class="space-y-6" x-data="{ open: false, cur: {} }">
    <!-- Header -->
    <div class="border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="megaphone" class="h-6 w-6 text-brand-600"></i> Announcements
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Company-wide updates and news from your team.</p>
    </div>

    @if($announcements->count())
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($announcements as $a)
                @php
                    $preview = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($a->bodyHtml()))), 180);
                    $meta = (optional($a->creator)->full_name ?? 'Admin') . ' · ' . $a->created_at->format('M j, Y');
                @endphp
                <div class="flex flex-col rounded-2xl border p-5 transition hover:shadow-md {{ $a->is_pinned ? 'border-brand-200 bg-brand-50/40 dark:border-brand-500/20 dark:bg-brand-500/5' : 'border-slate-200/80 bg-white dark:border-slate-700/80 dark:bg-slate-800' }}">
                    <div class="flex items-start gap-2 mb-2">
                        @if($a->is_pinned)<i data-lucide="pin" class="h-4 w-4 text-brand-600 mt-0.5 shrink-0"></i>@endif
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white line-clamp-2">{{ $a->title }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed line-clamp-4 flex-1">{{ $preview }}</p>
                    <div class="flex items-center justify-between gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                        <span class="text-[10px] text-slate-400 truncate">{{ $meta }}</span>
                        <button type="button"
                                @click="cur = @js(['title' => $a->title, 'body' => (string) $a->bodyHtml(), 'meta' => $meta, 'pinned' => (bool) $a->is_pinned]); open = true"
                                class="inline-flex items-center gap-1 rounded-lg bg-slate-50 border border-slate-200 px-3 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-slate-100 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-200 shrink-0">
                            <i data-lucide="eye" class="h-3 w-3"></i> View
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-12 text-center bg-white rounded-2xl border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
            <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                <i data-lucide="megaphone" class="h-6 w-6 text-slate-400"></i>
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No announcements yet</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">When your team posts an update, it will show up here.</p>
        </div>
    @endif

    {{-- Full announcement modal (teleported to body so the backdrop always
         covers the whole viewport, header included). --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
            <div class="relative w-full max-w-lg max-h-[80vh] overflow-y-auto rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                <div class="flex items-start justify-between gap-3 px-6 pt-5 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <template x-if="cur.pinned"><i data-lucide="pin" class="h-4 w-4 text-brand-600"></i></template>
                        <span x-text="cur.title"></span>
                    </h3>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-700 shrink-0"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <div class="px-6 py-4">
                    <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed prose prose-sm max-w-none dark:prose-invert" x-html="cur.body"></div>
                    <p class="text-[11px] text-slate-400 mt-4" x-text="cur.meta"></p>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
