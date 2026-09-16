{{--
    Hub tile grid — 3 columns of link cards, each with an icon, blurb, headline stat
    and optional meta chip / red badge. Used by the Team Management and Communication
    landing pages. Expects $tiles = [[route, icon, tone, title, text, stat, stat_label, meta?, badge?], …].
--}}
@php
    // Static class map so every colour is a literal Tailwind class (CDN build scans the DOM).
    $hubTones = [
        'brand'   => ['bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',       'group-hover:border-brand-300 dark:group-hover:border-brand-500/40'],
        'sky'     => ['bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',               'group-hover:border-sky-300 dark:group-hover:border-sky-500/40'],
        'emerald' => ['bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', 'group-hover:border-emerald-300 dark:group-hover:border-emerald-500/40'],
        'violet'  => ['bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400',   'group-hover:border-violet-300 dark:group-hover:border-violet-500/40'],
        'amber'   => ['bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',       'group-hover:border-amber-300 dark:group-hover:border-amber-500/40'],
        'rose'    => ['bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',           'group-hover:border-rose-300 dark:group-hover:border-rose-500/40'],
        'indigo'  => ['bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',   'group-hover:border-indigo-300 dark:group-hover:border-indigo-500/40'],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach($tiles as $tile)
        @php [$iconTone, $borderTone] = $hubTones[$tile['tone']] ?? $hubTones['brand']; @endphp
        <a href="{{ route($tile['route']) }}"
           class="group relative flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 {{ $borderTone }}">

            @if(!empty($tile['badge']))
                <span class="absolute right-4 top-4 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold leading-none text-white">{{ $tile['badge'] }}</span>
            @endif

            <div class="flex items-start gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $iconTone }}">
                    <i data-lucide="{{ $tile['icon'] }}" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-extrabold text-slate-800 dark:text-white">{{ $tile['title'] }}</p>
                    <p class="mt-0.5 text-xs leading-snug text-slate-500 dark:text-slate-400">{{ $tile['text'] }}</p>
                </div>
            </div>

            <div class="mt-5 flex items-end justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700/60">
                <div>
                    <p class="text-3xl font-black leading-none text-slate-900 dark:text-white">{{ $tile['stat'] }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $tile['stat_label'] }}</p>
                </div>
                <div class="flex items-center gap-2 text-right">
                    @if(!empty($tile['meta']))
                        <span class="rounded-lg bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-700/60 dark:text-slate-300">{{ $tile['meta'] }}</span>
                    @endif
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-slate-50 text-slate-400 transition group-hover:bg-slate-900 group-hover:text-white dark:bg-slate-700/60 dark:group-hover:bg-white dark:group-hover:text-slate-900">
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </span>
                </div>
            </div>
        </a>
    @endforeach
</div>
