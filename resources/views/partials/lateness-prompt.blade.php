{{-- Same-day "why were you late?" prompt. Shown to the employee on their next
     page load after a late clock-in; dismissible, but it comes back until the
     day is explained, and the month-end Lateness Review reads these answers. --}}
@php
    $lpDays = auth()->check()
        ? \App\Models\AttendanceExplanation::outstandingFor(auth()->user())
        : collect();
    $lpSnoozed = (int) session('lateness_prompt_snoozed_until', 0) > now()->timestamp;
    $lpToday = $lpDays->first(fn ($d) => $d->date->isToday());
@endphp

@if($lpDays->isNotEmpty())
<div x-data="{ open: {{ $lpSnoozed ? 'false' : 'true' }} }" class="mb-4">

    {{-- Always-present banner, so a dismissed prompt is never lost --}}
    <button type="button" @click="open = true"
            class="flex w-full items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left transition hover:bg-amber-100 dark:border-amber-500/25 dark:bg-amber-500/10 dark:hover:bg-amber-500/15">
        <i data-lucide="alarm-clock" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"></i>
        <span class="min-w-0 flex-1 text-sm text-amber-900 dark:text-amber-200">
            <span class="font-bold">{{ $lpDays->count() }} late {{ Str::plural('day', $lpDays->count()) }}</span>
            @if($lpToday) — including today @endif
            · tell us what happened
        </span>
        <span class="shrink-0 text-xs font-bold text-amber-700 dark:text-amber-300">Add reason</span>
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>

        <form method="POST" action="{{ route('attendance-explanations.store') }}"
              class="relative flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
              @keydown.escape.window="open = false">
            @csrf

            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-700/60">
                <div>
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white">
                        <i data-lucide="alarm-clock" class="h-4 w-4 text-amber-500"></i>
                        {{ $lpToday ? 'You were late today' : 'A few late days need a reason' }}
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Answering now keeps it accurate. These go on your month-end review, so you
                        won't be asked again.
                    </p>
                </div>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                @foreach($lpDays as $day)
                    <div>
                        <label for="lp-{{ $day->id }}" class="mb-1.5 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $day->date->format('D, d M Y') }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $day->late_minutes }} min late {{ $day->date->isToday() ? '· today' : '' }}
                            </span>
                        </label>
                        <textarea id="lp-{{ $day->id }}" name="reasons[{{ $day->id }}]" rows="2" maxlength="500"
                                  placeholder="What happened?"
                                  class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                @endforeach
                <p class="text-[11px] text-slate-400">Leave any blank to answer later.</p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-700/60">
                <button type="submit" formaction="{{ route('attendance-explanations.snooze') }}"
                        class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Not now</button>
                <button type="submit" class="btn-brand"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
            </div>
        </form>
    </div>
</div>
@endif
