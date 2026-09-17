{{-- One dashboard action card (compact, single-row: icon · title/text · arrow).
     Expects $card = [title, text, icon, tone, hue?, href?, event?].
     - href set  → renders as a link (arrow + hover)
     - event set → renders as a button that dispatches that Alpine window event (arrow + hover)
     - neither   → "Coming soon" placeholder
     - hue       → colour family for the arrow button (indigo, amber, rose, …) --}}
@php
    $__hasLink = !empty($card['href']);
    $__hasEvent = empty($card['href']) && !empty($card['event']);
    $__clickable = $__hasLink || $__hasEvent;
    $__tag = $__hasLink ? 'a' : ($__hasEvent ? 'button' : 'div');
    $__hue = $card['hue'] ?? 'slate';
@endphp
<{{ $__tag }}
    @if($__hasLink) href="{{ $card['href'] }}" @endif
    @if($__hasEvent) type="button" @click="$dispatch('{{ $card['event'] }}')" @endif
    class="group relative flex w-full items-center gap-4 text-left rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition dark:border-slate-700 dark:bg-slate-800 {{ $__clickable ? 'hover:-translate-y-0.5 hover:shadow-md hover:border-'.$__hue.'-300 dark:hover:border-'.$__hue.'-500/40' : '' }}">
    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i></span>
    <div class="min-w-0 flex-1">
        <h3 class="text-sm font-extrabold text-slate-800 dark:text-white">{{ $card['title'] }}</h3>
        <p class="mt-0.5 text-xs leading-snug text-slate-500 dark:text-slate-400">{{ $card['text'] }}</p>
    </div>
    @if($__clickable)
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-{{ $__hue }}-50 text-{{ $__hue }}-500 transition group-hover:bg-{{ $__hue }}-500 group-hover:text-white dark:bg-{{ $__hue }}-500/10 dark:text-{{ $__hue }}-400"><i data-lucide="arrow-right" class="h-4 w-4"></i></span>
    @else
        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-700 dark:text-slate-400">Coming soon</span>
    @endif
</{{ $__tag }}>
