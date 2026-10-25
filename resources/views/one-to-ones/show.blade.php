@extends('layouts.hr-app')

@section('title', 'One-to-one')
@section('breadcrumb', 'One-to-one')

@section('content')
@php
    $me = auth()->user();
    $other = $meeting->otherParty($me);
    $iHold = $me->id === $meeting->manager_id;
    $mine = $meeting->isAwaitingAnswerFrom($me);
    $colour = $meeting->statusColour();
@endphp

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ panel: null }">

    <a href="{{ route('one-to-ones.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> All one-to-ones
    </a>

    {{-- ---------------- What and when ---------------- --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $meeting->subject }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $meeting->employee->full_name ?? '—' }} with {{ $meeting->manager->full_name ?? '—' }}
                </p>
            </div>
            <span class="rounded-full bg-{{ $colour }}-100 px-3 py-1 text-xs font-bold text-{{ $colour }}-700 dark:bg-{{ $colour }}-500/15 dark:text-{{ $colour }}-300">
                {{ $meeting->statusLabel() }}
            </span>
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3 dark:border-slate-700/60">
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">When</dt>
                <dd class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-200">
                    {{ $meeting->scheduled_at->format('D j M Y') }}<br>
                    <span class="font-semibold text-slate-600 dark:text-slate-400">
                        {{ $meeting->scheduled_at->format('H:i') }}–{{ $meeting->endsAt()->format('H:i') }} {{ $tzLabel }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Where</dt>
                <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                    @if($meeting->is_online)
                        @if($meeting->meeting_link)
                            <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 font-bold text-brand-600 hover:underline dark:text-brand-400">
                                <i data-lucide="video" class="h-3.5 w-3.5"></i> Join
                            </a>
                        @else
                            Online
                        @endif
                    @else
                        {{ $meeting->location ?: '—' }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Proposed by</dt>
                <dd class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                    {{ $meeting->proposer->full_name ?? '—' }}
                    @if($meeting->aboutLabel())
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">About: {{ $meeting->aboutLabel() }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if($meeting->agenda)
            <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-700/60">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Agenda</p>
                <p class="mt-1.5 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $meeting->agenda }}</p>
            </div>
        @endif
    </div>

    {{-- What the rota and leave book know. Shown here too, because a time can
         become awkward after it was agreed — somebody books leave over it. --}}
    @if($warnings)
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/70 p-5 dark:border-amber-500/30 dark:bg-amber-500/5">
            <p class="mb-2 flex items-center gap-1.5 text-xs font-extrabold text-amber-900 dark:text-amber-200">
                <i data-lucide="triangle-alert" class="h-3.5 w-3.5"></i> Worth knowing about this time
            </p>
            <ul class="space-y-1 text-sm text-amber-800 dark:text-amber-200/90">
                @foreach($warnings as $w)
                    <li>• {{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ---------------- What can be done about it ---------------- --}}
    @if($meeting->isOpen())
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">

            @if($mine)
                <p class="mb-3 text-sm font-bold text-slate-800 dark:text-slate-200">
                    {{ $meeting->proposer->first_name ?? 'They' }} proposed this time. Does it work?
                </p>
            @endif

            <div class="flex flex-wrap items-center gap-2.5">
                @if($mine)
                    <form method="POST" action="{{ route('one-to-ones.confirm', $meeting) }}">
                        @csrf
                        <button class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">
                            <i data-lucide="check" class="h-4 w-4"></i> It works
                        </button>
                    </form>
                    <button type="button" @click="panel = panel === 'decline' ? null : 'decline'"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/50">
                        <i data-lucide="x" class="h-4 w-4"></i> It does not
                    </button>
                @elseif($meeting->status === 'proposed')
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Waiting on {{ $meeting->awaitingAnswerFrom()->first_name ?? 'them' }} to agree the time.
                    </p>
                @endif

                <button type="button" @click="panel = panel === 'move' ? null : 'move'"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/50">
                    <i data-lucide="calendar-sync" class="h-4 w-4"></i> Suggest another time
                </button>

                @if($iHold || $me->isAdmin())
                    <button type="button" @click="panel = panel === 'hold' ? null : 'hold'"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <i data-lucide="clipboard-check" class="h-4 w-4"></i> Write it up
                    </button>
                @endif

                <button type="button" @click="panel = panel === 'cancel' ? null : 'cancel'"
                        class="text-sm font-bold text-rose-600 hover:underline dark:text-rose-400">Cancel it</button>
            </div>

            {{-- It does not work --}}
            <div x-show="panel === 'decline'" x-cloak class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700/60">
                <form method="POST" action="{{ route('one-to-ones.decline', $meeting) }}">
                    @csrf
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Why not?</label>
                    <textarea name="note" rows="2" required maxlength="1000" placeholder="I am on site that afternoon — mornings are better."
                              class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
                    <p class="mt-1 text-xs text-slate-400">Suggesting a time instead usually gets this sorted in one go.</p>
                    <button class="mt-2.5 rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-rose-700">Send it back</button>
                </form>
            </div>

            {{-- Another time. The proposal changes hands rather than starting again. --}}
            <div x-show="panel === 'move'" x-cloak class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700/60">
                <form method="POST" action="{{ route('one-to-ones.reschedule', $meeting) }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date</label>
                            <input type="date" name="date" required min="{{ now()->toDateString() }}" value="{{ $meeting->scheduled_at->toDateString() }}"
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Time</label>
                            <input type="time" name="time" required value="{{ $meeting->scheduled_at->format('H:i') }}"
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                        </div>
                    </div>
                    <textarea name="note" rows="2" maxlength="1000" placeholder="Anything to say about the change (optional)"
                              class="mt-3 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
                    <button class="mt-2.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 transition hover:bg-brand-700">Propose this instead</button>
                </form>
            </div>

            {{-- It happened --}}
            @if($iHold || $me->isAdmin())
                <div x-show="panel === 'hold'" x-cloak class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700/60">
                    <form method="POST" action="{{ route('one-to-ones.hold', $meeting) }}">
                        @csrf
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">What was agreed</label>
                        <textarea name="outcome" rows="4" required maxlength="5000" placeholder="What was discussed, and what happens next."
                                  class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
                        <p class="mt-1 text-xs text-slate-400">{{ $meeting->employee->first_name ?? 'They' }} will see this.</p>

                        <label class="mt-3 mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Your own note <span class="font-normal normal-case text-slate-400">(only you and HR see this)</span>
                        </label>
                        <textarea name="private_note" rows="2" maxlength="5000"
                                  class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>

                        <button class="mt-3 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600">Record it</button>
                    </form>

                    {{-- A form of its own, and after the one above rather than
                         inside it: a meeting that did not happen has no outcome
                         to write, and the required field would stop somebody
                         saying so. --}}
                    <form method="POST" action="{{ route('one-to-ones.missed', $meeting) }}" class="mt-3">
                        @csrf
                        <span class="text-xs text-slate-400">or</span>
                        <button class="text-sm font-bold text-rose-600 hover:underline dark:text-rose-400">it did not happen</button>
                    </form>
                </div>
            @endif

            {{-- Call it off --}}
            <div x-show="panel === 'cancel'" x-cloak class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700/60">
                <form method="POST" action="{{ route('one-to-ones.cancel', $meeting) }}">
                    @csrf
                    <textarea name="note" rows="2" maxlength="1000" placeholder="Why it is off (optional)"
                              class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
                    <button class="mt-2.5 rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-rose-700">Cancel the one-to-one</button>
                </form>
            </div>
        </div>
    @endif

    {{-- ---------------- The record ---------------- --}}
    @if($meeting->outcome)
        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 dark:border-emerald-500/30 dark:bg-emerald-500/5">
            <p class="mb-1.5 flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">
                <i data-lucide="clipboard-check" class="h-3.5 w-3.5"></i> What was agreed
            </p>
            <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $meeting->outcome }}</p>
            @if($meeting->held_at)
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Written up {{ $meeting->held_at->diffForHumans() }}</p>
            @endif
        </div>
    @endif

    @if($seesPrivate && $meeting->private_note)
        <div class="mt-4 rounded-2xl border border-slate-300 border-dashed bg-slate-50 p-5 dark:border-slate-600 dark:bg-slate-900/40">
            <p class="mb-1.5 flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <i data-lucide="lock" class="h-3.5 w-3.5"></i> Your own note
            </p>
            <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $meeting->private_note }}</p>
        </div>
    @endif

    {{-- ---------------- How it got here ---------------- --}}
    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-extrabold text-slate-800 dark:text-white">
            <i data-lucide="history" class="h-4 w-4 text-slate-400"></i> How it got here
        </h2>

        <ol class="space-y-4">
            @foreach($meeting->events as $event)
                <li class="flex gap-3">
                    <div class="mt-1 h-2 w-2 shrink-0 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
                            {{ $event->label() }}
                            <span class="font-normal text-slate-500 dark:text-slate-400">
                                by {{ $event->actor->full_name ?? 'the system' }}
                            </span>
                        </p>
                        @if($event->new_time)
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                for {{ $event->new_time->format('D j M Y, H:i') }}
                            </p>
                        @endif
                        @if($event->note)
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $event->note }}</p>
                        @endif
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ optional($event->created_at)->diffForHumans() }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <form method="POST" action="{{ route('one-to-ones.note', $meeting) }}" class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-700/60">
            @csrf
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Add a note</label>
            <textarea name="note" rows="2" required maxlength="2000"
                      class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
            <button class="mt-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/50">Add it</button>
        </form>
    </div>
</div>
@endsection
