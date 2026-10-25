@extends('layouts.hr-app')

@section('title', 'One-to-ones')
@section('breadcrumb', 'One-to-ones')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="bookingPage()">

    <div class="mb-6 flex flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700/60">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <i data-lucide="users-round" class="h-6 w-6 text-brand-500"></i> One-to-ones
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                A time is only booked once the other person agrees to it. Times are shown in {{ $tzLabel }}.
            </p>
        </div>

        @if($bookable->isNotEmpty())
            <button type="button" @click="open()"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 transition hover:bg-brand-700">
                <i data-lucide="calendar-plus" class="h-4 w-4"></i> Book a one-to-one
            </button>
        @endif
    </div>

    {{-- Waiting on you. First, because it is the only list the viewer can clear
         by themselves, and a proposal nobody answers is just a diary entry. --}}
    @php $yours = $mine->filter(fn ($m) => $m->isAwaitingAnswerFrom(auth()->user())); @endphp
    @if($yours->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-500/30 dark:bg-amber-500/5">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold text-amber-900 dark:text-amber-200">
                <i data-lucide="hand" class="h-4 w-4"></i> Waiting on you
            </h2>
            <div class="space-y-2.5">
                @foreach($yours as $m)
                    @include('one-to-ones.partials.row', ['m' => $m, 'tone' => 'amber'])
                @endforeach
            </div>
        </div>
    @endif

    {{-- Agreed, the time has gone, and nobody has said whether it happened. --}}
    @if($loose->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="mb-1 flex items-center gap-2 text-sm font-extrabold text-slate-800 dark:text-white">
                <i data-lucide="circle-help" class="h-4 w-4 text-slate-400"></i> Did these happen?
            </h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                The time has passed and nothing was written up. Say what was agreed, or that it did not happen.
            </p>
            <div class="space-y-2.5">
                @foreach($loose as $m)
                    @include('one-to-ones.partials.row', ['m' => $m, 'tone' => 'slate'])
                @endforeach
            </div>
        </div>
    @endif

    {{-- Coming up --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold text-slate-800 dark:text-white">
            <i data-lucide="calendar-days" class="h-4 w-4 text-brand-500"></i> Coming up
        </h2>

        @php $ahead = $mine->reject(fn ($m) => $m->isAwaitingAnswerFrom(auth()->user())); @endphp
        @if($ahead->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400">
                Nothing booked.
                @if($bookable->isNotEmpty())
                    <button type="button" @click="open()" class="font-bold text-brand-600 hover:underline dark:text-brand-400">Book one</button>
                @else
                    You have no manager set and nobody reports to you, so there is nobody to book with yet — HR can set that up.
                @endif
            </p>
        @else
            <div class="space-y-2.5">
                @foreach($ahead as $m)
                    @include('one-to-ones.partials.row', ['m' => $m, 'tone' => 'slate'])
                @endforeach
            </div>
        @endif
    </div>

    {{-- Everyone's, for whoever is accountable for these happening at all --}}
    @if($everyones->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold text-slate-800 dark:text-white">
                <i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i> Across the organisation
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 pr-4">When</th>
                            <th class="py-2 pr-4">With</th>
                            <th class="py-2 pr-4">Held by</th>
                            <th class="py-2 pr-4">About</th>
                            <th class="py-2">State</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($everyones as $m)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="py-2.5 pr-4 whitespace-nowrap">
                                    <a href="{{ route('one-to-ones.show', $m) }}" class="font-bold text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $m->scheduled_at->format('D j M, H:i') }}
                                    </a>
                                </td>
                                <td class="py-2.5 pr-4">{{ $m->employee->full_name ?? '—' }}</td>
                                <td class="py-2.5 pr-4 text-slate-500 dark:text-slate-400">{{ $m->manager->full_name ?? '—' }}</td>
                                <td class="py-2.5 pr-4 text-slate-500 dark:text-slate-400">{{ $m->subject }}</td>
                                <td class="py-2.5">
                                    <span class="rounded-full bg-{{ $m->statusColour() }}-100 px-2 py-0.5 text-xs font-bold text-{{ $m->statusColour() }}-700 dark:bg-{{ $m->statusColour() }}-500/15 dark:text-{{ $m->statusColour() }}-300">
                                        {{ $m->statusLabel() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- What has already been had --}}
    @if($past->isNotEmpty())
        <div x-data="{ shown: false }" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <button type="button" @click="shown = !shown" class="flex w-full items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-extrabold text-slate-800 dark:text-white">
                    <i data-lucide="history" class="h-4 w-4 text-slate-400"></i> Earlier ({{ $past->count() }})
                </h2>
                <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400" x-bind:class="shown && 'rotate-180'"></i>
            </button>
            <div x-show="shown" x-cloak class="mt-4 space-y-2.5">
                @foreach($past as $m)
                    @include('one-to-ones.partials.row', ['m' => $m, 'tone' => 'slate'])
                @endforeach
            </div>
        </div>
    @endif

    {{-- ---------------- Booking ---------------- --}}
    <div x-show="form.open" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="absolute inset-0 bg-slate-900/50" @click="form.open = false"></div>
        <div class="relative mx-auto my-8 w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <form method="POST" action="{{ route('one-to-ones.store') }}">
                @csrf
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-700/60">
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Book a one-to-one</h3>
                    <button type="button" @click="form.open = false" class="text-slate-400 hover:text-slate-700">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <div class="space-y-4 px-6 py-5">
                    {{-- Carried from wherever this was opened — the probation
                         list knows which review it wants booked. --}}
                    <input type="hidden" name="about_key" value="{{ $prefill['about_key'] }}">
                    <input type="hidden" name="about_id" value="{{ $prefill['about_id'] }}">

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">With</label>
                        <select name="with_user_id" x-model="form.with" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                            <option value="">Choose somebody…</option>
                            @foreach($bookable as $person)
                                <option value="{{ $person->id }}">{{ $person->first_name }} {{ $person->last_name }}@if($person->job_title) — {{ $person->job_title }}@endif</option>
                            @endforeach
                        </select>
                    </div>

                    @if($canName)
                        {{-- HR arranging somebody else's conversation. A probation
                             review is often the manager's to hold even when HR booked it. --}}
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Held by</label>
                            <select name="holder_id" x-model="form.holder"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                <option value="">Me</option>
                                @foreach($bookable as $person)
                                    <option value="{{ $person->id }}">{{ $person->first_name }} {{ $person->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date</label>
                            <input type="date" name="date" x-model="form.date" required min="{{ now()->toDateString() }}"
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Time</label>
                            <input type="time" name="time" x-model="form.time" required
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">How long</label>
                        <select name="duration_minutes" x-model="form.minutes"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                            @foreach ([15, 30, 45, 60, 90] as $mins)
                                <option value="{{ $mins }}" @selected($mins === 30)>{{ $mins }} minutes</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- What the rota and the leave book already know about this
                         time. Shown before the invitation goes out, not after
                         somebody fails to turn up. --}}
                    <template x-if="warnings.length">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 dark:border-amber-500/30 dark:bg-amber-500/10">
                            <p class="mb-1.5 flex items-center gap-1.5 text-xs font-extrabold text-amber-900 dark:text-amber-200">
                                <i data-lucide="triangle-alert" class="h-3.5 w-3.5"></i> Worth knowing
                            </p>
                            <ul class="space-y-1 text-xs text-amber-800 dark:text-amber-200/90">
                                <template x-for="w in warnings" :key="w">
                                    <li x-text="'• ' + w"></li>
                                </template>
                            </ul>
                            <p class="mt-2 text-[11px] text-amber-700/80 dark:text-amber-300/70">
                                You can still book it — this is only what the system already knew.
                            </p>
                        </div>
                    </template>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">What it is about</label>
                        <input type="text" name="subject" required maxlength="160" placeholder="Monthly catch-up"
                               value="{{ $prefill['subject'] }}"
                               class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Agenda <span class="font-normal normal-case text-slate-400">(optional)</span></label>
                        <textarea name="agenda" rows="3" maxlength="5000"
                                  class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
                    </div>

                    <div x-data="{ online: false }" class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="is_online" value="1" x-model="online"
                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                            Online
                        </label>
                        <template x-if="online">
                            <input type="url" name="meeting_link" maxlength="500" placeholder="https://…"
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                        </template>
                        <template x-if="!online">
                            <input type="text" name="location" maxlength="160" placeholder="Where — meeting room, site, a walk"
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4 dark:border-slate-700/60">
                    <button type="button" @click="form.open = false" class="text-sm font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 transition hover:bg-brand-700">
                        <i data-lucide="send" class="h-4 w-4"></i> Propose it
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function bookingPage() {
        return {
            form: {
                // Open already if somebody arrived here to book a particular
                // conversation, rather than making them click again.
                open: {{ $prefill['with'] ? 'true' : 'false' }},
                with: '{{ $prefill['with'] }}',
                holder: '',
                date: '{{ now()->addDay()->toDateString() }}',
                time: '10:00',
                minutes: '30',
            },
            warnings: [],
            timer: null,

            open() {
                this.form.open = true;
                this.$nextTick(() => window.lucide && window.lucide.createIcons());
            },

            init() {
                // One watcher over the four fields that can change the answer.
                // Debounced, because a typed time fires on every keystroke and
                // the question is only worth asking once the typing stops.
                this.$watch('form', () => this.schedule(), { deep: true });

                // Opened already filled in, so ask straight away — otherwise
                // the one booking that skipped the form also skips the check.
                if (this.form.with) this.schedule();
            },

            schedule() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.ask(), 350);
            },

            async ask() {
                if (!this.form.with || !this.form.date || !this.form.time) { this.warnings = []; return; }

                const q = new URLSearchParams({
                    with_user_id: this.form.with,
                    date: this.form.date,
                    time: this.form.time,
                    duration_minutes: this.form.minutes || 30,
                });
                if (this.form.holder) q.set('holder_id', this.form.holder);

                try {
                    const res = await fetch('{{ route('one-to-ones.check') }}?' + q.toString(), {
                        headers: { 'Accept': 'application/json' },
                    });
                    // A clash check that cannot answer must not block a booking,
                    // so a failed lookup shows nothing rather than a false all-clear.
                    this.warnings = res.ok ? ((await res.json()).warnings || []) : [];
                } catch (e) {
                    this.warnings = [];
                }
                this.$nextTick(() => window.lucide && window.lucide.createIcons());
            },
        };
    }
</script>
@endsection
