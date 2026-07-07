@php
    $announcements = \App\Models\Announcement::active()
        ->orderByDesc('is_pinned')
        ->latest()
        ->take(5)
        ->get();
@endphp

@if($announcements->count())
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-center gap-3 mb-4">
            <div class="h-10 w-10 rounded-xl bg-brand-50 dark:bg-brand-500/10 border border-brand-100/50 dark:border-brand-500/20 flex items-center justify-center text-brand-600 dark:text-brand-400">
                <i data-lucide="megaphone" class="h-5 w-5"></i>
            </div>
            <h2 class="text-base font-semibold text-slate-800 dark:text-white">Announcements</h2>
        </div>

        <div class="space-y-3">
            @foreach($announcements as $a)
                <div class="rounded-xl border border-slate-100 p-4 dark:border-slate-700/60 {{ $a->is_pinned ? 'bg-brand-50/40 dark:bg-brand-500/5' : 'bg-slate-50/50 dark:bg-slate-900/30' }}">
                    <div class="flex items-center gap-2">
                        @if($a->is_pinned)<i data-lucide="pin" class="h-3.5 w-3.5 text-brand-600"></i>@endif
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $a->title }}</h3>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed">{!! $a->bodyHtml() !!}</p>
                    <p class="text-[11px] text-slate-400 mt-2">{{ optional($a->creator)->full_name ?? 'Admin' }} · {{ $a->created_at->diffForHumans() }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif
