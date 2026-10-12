{{-- "You're back — how did it go?" prompt. Shown on the employee's next page
     load after an unplanned leave ends; dismissible, but it comes back until
     the leave is written up, and the month-end document reads these answers. --}}
@php
    $rpLeaves = auth()->check()
        ? \App\Models\TimeOffRequest::outstandingReturnsFor(auth()->user())
        : collect();
    $rpSnoozed = (int) session('return_prompt_snoozed_until', 0) > now()->timestamp;
    $rpJustBack = $rpLeaves->first(fn ($lv) => \Illuminate\Support\Carbon::parse($lv->end_date)->addDay()->isToday());
    // Two modals opening on top of each other would be unusable. This is the
    // least urgent of the three, so if the lateness prompt or the announcements
    // popup is about to open, this one waits as a banner and opens on a click.
    $rpLatenessOpen = auth()->check()
        && (int) session('lateness_prompt_snoozed_until', 0) <= now()->timestamp
        && \App\Models\AttendanceExplanation::outstandingFor(auth()->user())->isNotEmpty();
    $rpAnnouncementsOpen = auth()->check()
        && request()->routeIs('dashboard')
        && \App\Models\Announcement::unreadFor(auth()->user())->exists();
    // A refused save flashes its message behind the overlay, where it cannot be
    // read, so the prompt reopens and carries the message inside it.
    $rpError = session('error');
@endphp

@if($rpLeaves->isNotEmpty())
<div x-data="{ open: {{ $rpError ? 'true' : (($rpSnoozed || $rpLatenessOpen || $rpAnnouncementsOpen) ? 'false' : 'true') }} }" class="mb-4">

    {{-- Always-present banner, so a dismissed prompt is never lost --}}
    <button type="button" @click="open = true"
            class="flex w-full items-center gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-left transition hover:bg-sky-100 dark:border-sky-500/25 dark:bg-sky-500/10 dark:hover:bg-sky-500/15">
        <i data-lucide="undo-2" class="h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400"></i>
        <span class="min-w-0 flex-1 text-sm text-sky-900 dark:text-sky-200">
            <span class="font-bold">{{ $rpLeaves->count() }} {{ Str::plural('return', $rpLeaves->count()) }}</span>
            {{ $rpJustBack ? ' — welcome back' : '' }}
            · tell us how it went
        </span>
        <span class="shrink-0 text-xs font-bold text-sky-700 dark:text-sky-300">Add note</span>
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>

        <form method="POST" action="{{ route('leave-returns.store') }}"
              class="relative flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
              @keydown.escape.window="open = false">
            @csrf

            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-700/60">
                <div>
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white">
                        <i data-lucide="undo-2" class="h-4 w-4 text-sky-500"></i>
                        {{ $rpJustBack ? 'Welcome back' : ($rpLeaves->count() === 1 ? 'Your return needs a note' : 'A few returns need a note') }}
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        A line here replaces the return-to-work chat. It goes onto the HR document
                        for this leave, so you won't be asked again.
                    </p>
                </div>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                @if($rpError)
                    <p class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{{ $rpError }}</p>
                @endif
                @foreach($rpLeaves as $lv)
                    <div>
                        <label for="rp-{{ $lv->id }}" class="mb-1.5 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ optional($lv->policy)->name ?? 'Leave' }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $lv->date_label }} · back {{ \Illuminate\Support\Carbon::parse($lv->end_date)->addDay()->format('d M Y') }}
                            </span>
                        </label>
                        @if(trim((string) $lv->reason) !== '')
                            <p class="mb-1.5 text-xs text-slate-400">You applied saying: <span class="italic">“{{ $lv->reason }}”</span></p>
                        @endif
                        <textarea id="rp-{{ $lv->id }}" name="notes[{{ $lv->id }}]" rows="2" maxlength="1000"
                                  placeholder="{{ optional($lv->policy)->isWorkFromHome() ? 'How did working from home go, and is there anything we should know?' : 'How are you now, and is there anything we should know?' }}"
                                  class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                @endforeach
                <p class="text-[11px] text-slate-400">Leave any blank to answer later.</p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-700/60">
                <button type="submit" formaction="{{ route('leave-returns.snooze') }}"
                        class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Not now</button>
                <button type="submit" class="btn-brand"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
            </div>
        </form>
    </div>
</div>
@endif
