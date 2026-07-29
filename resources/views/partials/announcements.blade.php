@php
    $announcements = \App\Models\Announcement::active()
        ->orderByDesc('is_pinned')
        ->latest()
        ->take(6)
        ->get();
@endphp

@if($announcements->count())
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 dark:bg-slate-800 dark:border-slate-700"
         x-data="{ open: false, cur: {} }">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-brand-50 dark:bg-brand-500/10 border border-brand-100/50 dark:border-brand-500/20 flex items-center justify-center text-brand-600 dark:text-brand-400">
                    <i data-lucide="megaphone" class="h-5 w-5"></i>
                </div>
                <h2 class="text-base font-semibold text-slate-800 dark:text-white">Announcements</h2>
            </div>
            <a href="{{ route('announcements.all') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400">View all →</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($announcements as $a)
                @php
                    $preview = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($a->bodyHtml()))), 140);
                    $meta = (optional($a->creator)->full_name ?? 'Admin') . ' · ' . $a->created_at->diffForHumans();
                @endphp
                <div class="flex flex-col rounded-xl border p-4 transition hover:shadow-sm {{ $a->is_pinned ? 'border-brand-200 bg-brand-50/40 dark:border-brand-500/20 dark:bg-brand-500/5' : 'border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-900/30' }}">
                    <div class="flex items-start gap-2">
                        @if($a->is_pinned)<i data-lucide="pin" class="h-3.5 w-3.5 text-brand-600 mt-0.5 shrink-0"></i>@endif
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white line-clamp-1">{{ $a->title }}</h3>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed line-clamp-3 flex-1">{{ $preview }}</p>
                    <div class="flex items-center justify-between gap-2 mt-3">
                        <span class="text-[10px] text-slate-400 truncate">{{ $meta }}</span>
                        <button type="button"
                                @click="cur = @js(['title' => $a->title, 'body' => (string) $a->bodyHtml(), 'meta' => $meta, 'pinned' => (bool) $a->is_pinned]); open = true"
                                class="inline-flex items-center gap-1 rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-200 shrink-0">
                            <i data-lucide="eye" class="h-3 w-3"></i> View
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Full announcement modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
            <div class="relative w-full max-w-lg max-h-[80vh] overflow-y-auto rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                <div class="flex items-start justify-between gap-3 px-6 pt-5 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <template x-if="cur.pinned"><i data-lucide="pin" class="h-4 w-4 text-brand-600"></i></template>
                        <span x-text="cur.title"></span>
                    </h3>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-700 shrink-0"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <div class="px-6 py-4">
                    <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed" x-html="cur.body"></div>
                    <p class="text-[11px] text-slate-400 mt-4" x-text="cur.meta"></p>
                </div>
            </div>
        </div>
    </div>
@endif
