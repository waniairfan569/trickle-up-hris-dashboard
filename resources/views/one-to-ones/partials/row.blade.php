{{-- One booking, as a line. Used in every list on the page so a conversation
     looks the same whether it is waiting, agreed or long over. --}}
@php
    $me = auth()->user();
    $other = $m->otherParty($me);
    $colour = $m->statusColour();
@endphp

<a href="{{ route('one-to-ones.show', $m) }}"
   class="flex items-start gap-3.5 rounded-xl border border-slate-200/70 bg-white p-3.5 transition hover:border-brand-300 hover:shadow-sm dark:border-slate-700/60 dark:bg-slate-900/40 dark:hover:border-brand-500/40">

    <div class="w-14 shrink-0 text-center">
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $m->scheduled_at->format('D') }}</p>
        <p class="text-lg font-extrabold leading-tight text-slate-900 dark:text-white">{{ $m->scheduled_at->format('j') }}</p>
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $m->scheduled_at->format('M') }}</p>
    </div>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $m->subject }}</p>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            {{ $m->scheduled_at->format('H:i') }}–{{ $m->endsAt()->format('H:i') }}
            · with {{ $other->full_name ?? 'someone' }}
            @if($m->user_id === $me->id && $other)
                <span class="text-slate-400">(they are holding it)</span>
            @endif
        </p>

        <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-{{ $colour }}-100 px-2 py-0.5 text-[11px] font-bold text-{{ $colour }}-700 dark:bg-{{ $colour }}-500/15 dark:text-{{ $colour }}-300">
                {{ $m->statusLabel() }}
            </span>

            @if($m->aboutLabel())
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                    {{ $m->aboutLabel() }}
                </span>
            @endif

            @if($m->is_online)
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-brand-600 dark:text-brand-400">
                    <i data-lucide="video" class="h-3 w-3"></i> Online
                </span>
            @elseif($m->location)
                <span class="inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                    <i data-lucide="map-pin" class="h-3 w-3"></i> {{ $m->location }}
                </span>
            @endif
        </div>
    </div>

    <i data-lucide="chevron-right" class="mt-1 h-4 w-4 shrink-0 text-slate-300 dark:text-slate-600"></i>
</a>
