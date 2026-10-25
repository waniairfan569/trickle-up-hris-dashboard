@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="coverageBoard()">

    <div class="mb-6 flex flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700/60">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <i data-lucide="user-round-x" class="h-6 w-6 text-brand-500"></i> Shift coverage
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Shifts whose person is on approved leave. Putting somebody on a day here changes that day only — their usual shift is untouched.
            </p>
        </div>

        <form method="GET" action="{{ route('shifts.coverage') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">From</label>
                <input type="date" name="from" value="{{ $from->toDateString() }}"
                       class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Looking ahead</label>
                <select name="days" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                    @foreach ([7 => '1 week', 14 => '2 weeks', 30 => '1 month', 60 => '2 months'] as $value => $label)
                        <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600">
                <i data-lucide="search" class="h-4 w-4"></i> Show
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0"></i> {{ session('success') }}
        </div>
    @endif

    @if($gaps->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-800/50">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                <i data-lucide="calendar-check" class="h-7 w-7"></i>
            </div>
            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Nobody rostered is away</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                Between {{ $from->format('j M') }} and {{ $to->format('j M') }}, every shift has its usual person on it.
            </p>
        </div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/5">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-400/80">Nobody on them</p>
                <p class="mt-1 text-3xl font-extrabold text-amber-800 dark:text-amber-300">{{ $open }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/5">
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400/80">Covered</p>
                <p class="mt-1 text-3xl font-extrabold text-emerald-800 dark:text-emerald-300">{{ $gaps->count() - $open }}</p>
            </div>
        </div>

        <div class="space-y-6">
            @foreach($byDate as $date => $rows)
                @php $day = \Carbon\Carbon::parse($date); @endphp
                <div>
                    <h2 class="mb-2 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ $day->format('l j F') }}
                        @if($day->isToday())
                            <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">Today</span>
                        @endif
                    </h2>

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800/50">
                        @foreach($rows as $gap)
                            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700/60">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-block h-9 w-1.5 shrink-0 rounded-full" style="background-color: {{ $gap->shift->color ?? '#3B82F6' }}"></span>
                                    <div>
                                        <p class="font-extrabold text-slate-900 dark:text-white">
                                            {{ $gap->shift->name }}
                                            <span class="ml-1 text-sm font-semibold text-slate-400">
                                                {{ substr($gap->shift->start_time, 0, 5) }}–{{ substr($gap->shift->end_time, 0, 5) }}
                                            </span>
                                        </p>
                                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                            {{ trim(($gap->user->first_name ?? '') . ' ' . ($gap->user->last_name ?? '')) }}
                                            is on {{ strtolower($gap->reason) }}
                                            @if($gap->part_day)
                                                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600 dark:bg-slate-700 dark:text-slate-300">part day</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if($gap->cover)
                                    <div class="flex items-center gap-2">
                                        @php
                                            // Somebody who put themselves on it set both fields to
                                            // their own id. Worth showing: cover that was volunteered
                                            // for and cover that was handed out are different news.
                                            $volunteered = $gap->cover->assigned_by === $gap->cover->user_id;
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-1.5 text-sm font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            <i data-lucide="user-round-check" class="h-4 w-4"></i>
                                            {{ trim(($gap->cover->user->first_name ?? '') . ' ' . ($gap->cover->user->last_name ?? '')) }}
                                            @if($volunteered)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">took it</span>
                                            @endif
                                        </span>
                                        <form method="POST" action="{{ route('shifts.coverage.uncover', $gap->cover) }}"
                                              onsubmit="return confirm('Remove this cover? The shift goes back to having nobody on it.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                                <i data-lucide="x" class="h-4 w-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <button type="button"
                                            @click="open('{{ $date }}', {{ $gap->shift->id }}, '{{ addslashes($gap->shift->name) }}', {{ $gap->user->id }}, '{{ addslashes(trim(($gap->user->first_name ?? '') . ' ' . ($gap->user->last_name ?? ''))) }}')"
                                            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 shadow-sm transition hover:bg-brand-700">
                                        <i data-lucide="user-round-plus" class="h-4 w-4"></i> Find cover
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Picker. Kept outside every other form on the page so its own form is never nested. --}}
    <div x-show="showing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showing = false"></div>

        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
            <div class="border-b border-slate-200 p-5 dark:border-slate-700">
                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Cover <span x-text="shiftName"></span></h3>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    <span x-text="prettyDate"></span> · standing in for <span x-text="awayName"></span>
                </p>
            </div>

            <form method="POST" action="{{ route('shifts.coverage.cover') }}">
                @csrf
                <input type="hidden" name="date" :value="date">
                <input type="hidden" name="shift_id" :value="shiftId">
                <input type="hidden" name="covers_user_id" :value="awayId">

                <div class="max-h-80 overflow-y-auto p-2">
                    <template x-if="loading">
                        <p class="p-6 text-center text-sm text-slate-500">Looking for who is free…</p>
                    </template>

                    <template x-if="!loading && people.length === 0">
                        <p class="p-6 text-center text-sm text-slate-500">Everybody else is away too.</p>
                    </template>

                    <template x-for="person in people" :key="person.id">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <input type="radio" name="user_id" :value="person.id" required
                                   class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="flex-1">
                                <span class="block font-bold text-slate-900 dark:text-white" x-text="person.name"></span>
                                <span class="block text-xs" :class="person.free ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'" x-text="person.note"></span>
                            </span>
                        </label>
                    </template>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 p-4 dark:border-slate-700">
                    <button type="button" @click="showing = false" class="rounded-xl px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" :disabled="people.length === 0"
                            class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-bold text-slate-900 transition hover:bg-brand-700 disabled:opacity-40">
                        Assign cover
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function coverageBoard() {
    return {
        showing: false, loading: false, people: [],
        date: '', shiftId: null, shiftName: '', awayId: null, awayName: '', prettyDate: '',

        open(date, shiftId, shiftName, awayId, awayName) {
            this.date = date;
            this.shiftId = shiftId;
            this.shiftName = shiftName;
            this.awayId = awayId;
            this.awayName = awayName;
            this.prettyDate = new Date(date + 'T00:00:00')
                .toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });
            this.people = [];
            this.showing = true;
            this.load();
        },

        load() {
            this.loading = true;
            const url = '{{ route('shifts.coverage.candidates') }}'
                + '?date=' + encodeURIComponent(this.date)
                + '&shift_id=' + this.shiftId
                + '&instead_of=' + this.awayId;

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(rows => { this.people = rows; })
                .catch(() => { this.people = []; })
                .finally(() => {
                    this.loading = false;
                    this.$nextTick(() => window.lucide && window.lucide.createIcons());
                });
        },
    };
}
</script>
@endsection
