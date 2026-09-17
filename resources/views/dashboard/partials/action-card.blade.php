{{-- One dashboard action card. Expects $card = [title, text, icon, tone, href?].
     Renders as a link (arrow + hover) when href is set, else a "Coming soon" placeholder. --}}
@php $__hasLink = !empty($card['href']); @endphp
<{{ $__hasLink ? 'a' : 'div' }} @if($__hasLink) href="{{ $card['href'] }}" @endif
    class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition dark:border-slate-700 dark:bg-slate-800 {{ $__hasLink ? 'block hover:-translate-y-0.5 hover:shadow-md hover:border-brand-300 dark:hover:border-brand-500/40' : '' }}">
    <div class="flex items-start justify-between gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i></span>
        @if($__hasLink)
            <span class="grid h-8 w-8 place-items-center rounded-full bg-slate-50 text-slate-400 transition group-hover:bg-slate-900 group-hover:text-white dark:bg-slate-700/60 dark:group-hover:bg-white dark:group-hover:text-slate-900"><i data-lucide="arrow-right" class="h-4 w-4"></i></span>
        @else
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-700 dark:text-slate-400">Coming soon</span>
        @endif
    </div>
    <h3 class="mt-4 text-sm font-extrabold text-slate-800 dark:text-white">{{ $card['title'] }}</h3>
    <p class="mt-1 text-xs leading-snug text-slate-500 dark:text-slate-400">{{ $card['text'] }}</p>
</{{ $__hasLink ? 'a' : 'div' }}>
