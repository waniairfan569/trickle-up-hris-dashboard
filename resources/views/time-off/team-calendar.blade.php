@extends('layouts.hr-app')

@section('title', 'Team Calendar')
@section('breadcrumb', 'Team Calendar')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="teamCalendar()">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <a href="{{ route('time-off.index') }}" class="text-brand-600 hover:underline">Time-Off</a> /
                Team Calendar
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                See who's on leave — browse by month, week or day.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('time-off.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Request Time Off
            </a>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
        <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- Left: navigation + title --}}
            <div class="flex items-center gap-2">
                <button type="button" @click="shift(-1)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700" title="Previous">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                </button>
                <button type="button" @click="goToday()" class="rounded-lg border border-slate-200 px-3 h-9 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Today</button>
                <button type="button" @click="shift(1)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700" title="Next">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                </button>
                <h3 class="ml-2 text-base font-extrabold text-slate-900 dark:text-white" x-text="title"></h3>
            </div>

            {{-- Right: view switch + year + filters --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- View switch --}}
                <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-600">
                    <template x-for="v in ['month','week','day']" :key="v">
                        <button type="button" @click="view = v; selected = null"
                                class="rounded-md px-3 py-1.5 text-xs font-bold capitalize transition"
                                :class="view === v ? 'bg-brand-600 text-slate-900' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                                x-text="v"></button>
                    </template>
                </div>

                {{-- Year --}}
                <select x-model.number="year" @change="setYear(year)" class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    <template x-for="y in years" :key="y"><option :value="y" x-text="y"></option></template>
                </select>

                {{-- Status filter --}}
                <select x-model="statusFilter" class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    <option value="all">All statuses</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                </select>

                {{-- Search --}}
                <div class="relative">
                    <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400"></i>
                    <input type="text" x-model="search" placeholder="Employee…" class="h-9 w-36 rounded-lg border border-slate-200 bg-white pl-8 pr-2 text-xs text-slate-700 focus:border-brand-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                </div>
            </div>
        </div>

        {{-- ===================== MONTH VIEW ===================== --}}
        <div x-show="view === 'month'" class="border-t border-slate-100 dark:border-slate-700/60">
            <div class="grid grid-cols-7 border-b border-slate-100 dark:border-slate-700/60">
                <template x-for="d in dayNames" :key="d">
                    <div class="px-2 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-400" x-text="d"></div>
                </template>
            </div>
            <div>
                <template x-for="(week, wi) in monthMatrix()" :key="wi">
                    <div class="grid grid-cols-7">
                        <template x-for="cell in week" :key="cell.key">
                            <button type="button" @click="selectDay(cell.key)"
                                    class="relative min-h-[92px] border-b border-r border-slate-100 p-1.5 text-left transition hover:bg-slate-50 dark:border-slate-700/60 dark:hover:bg-slate-700/30"
                                    :class="[
                                        cell.inMonth ? '' : 'bg-slate-50/40 dark:bg-slate-900/30',
                                        cell.leaves.length ? 'bg-brand-50/50 dark:bg-brand-500/5' : '',
                                        selected === cell.key ? 'ring-2 ring-inset ring-brand-500' : ''
                                    ]">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                          :class="cell.isToday ? 'bg-brand-600 text-slate-900' : (cell.inMonth ? 'text-slate-700 dark:text-slate-300' : 'text-slate-300 dark:text-slate-600')"
                                          x-text="cell.day"></span>
                                    <span x-show="cell.leaves.length" class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                                </div>
                                {{-- initials chips --}}
                                <div class="mt-1 space-y-0.5" x-show="cell.leaves.length">
                                    <template x-for="lv in cell.leaves.slice(0,3)" :key="lv.id">
                                        <div class="flex items-center gap-1 rounded px-1 py-0.5 text-[10px] font-semibold truncate"
                                             :class="colorFor(lv.name).chip">
                                            <span class="h-1.5 w-1.5 rounded-full shrink-0" :class="colorFor(lv.name).dot"></span>
                                            <span class="truncate" x-text="lv.name"></span>
                                        </div>
                                    </template>
                                    <div x-show="cell.leaves.length > 3" class="px-1 text-[10px] font-bold text-slate-400"
                                         x-text="'+' + (cell.leaves.length - 3) + ' more'"></div>
                                </div>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- ===================== WEEK VIEW ===================== --}}
        <div x-show="view === 'week'" class="border-t border-slate-100 dark:border-slate-700/60 overflow-x-auto">
            <div class="grid min-w-[720px] grid-cols-7">
                <template x-for="cell in weekMatrix()" :key="cell.key">
                    <button type="button" @click="selectDay(cell.key)"
                            class="min-h-[200px] border-r border-slate-100 p-2 text-left align-top transition hover:bg-slate-50 dark:border-slate-700/60 dark:hover:bg-slate-700/30"
                            :class="[cell.leaves.length ? 'bg-brand-50/40 dark:bg-brand-500/5' : '', selected === cell.key ? 'ring-2 ring-inset ring-brand-500' : '']">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase text-slate-400" x-text="cell.name"></span>
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                  :class="cell.isToday ? 'bg-brand-600 text-slate-900' : 'text-slate-600 dark:text-slate-300'" x-text="cell.day"></span>
                        </div>
                        <div class="space-y-1">
                            <template x-for="lv in cell.leaves" :key="lv.id">
                                <div class="rounded-md px-1.5 py-1 text-[11px] font-semibold" :class="colorFor(lv.name).chip">
                                    <div class="flex items-center gap-1">
                                        <span class="h-1.5 w-1.5 rounded-full shrink-0" :class="colorFor(lv.name).dot"></span>
                                        <span class="truncate" x-text="lv.name"></span>
                                    </div>
                                    <div class="truncate text-[10px] font-medium opacity-70" x-text="lv.policy"></div>
                                </div>
                            </template>
                            <div x-show="!cell.leaves.length" class="text-[11px] text-slate-300 dark:text-slate-600">—</div>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        {{-- ===================== DAY VIEW ===================== --}}
        <div x-show="view === 'day'" class="border-t border-slate-100 p-5 dark:border-slate-700/60">
            <template x-if="dayLeaves().length">
                <div class="space-y-2">
                    <template x-for="lv in dayLeaves()" :key="lv.id">
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 dark:border-slate-700/60">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="colorFor(lv.name).chip" x-text="lv.initials"></div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold text-slate-900 dark:text-white truncate" x-text="lv.name"></div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="colorFor(lv.name).dot"></span>
                                    <span x-text="lv.policy"></span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-300" x-text="rangeLabel(lv)"></div>
                                <span class="mt-0.5 inline-block rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                                      :class="lv.status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'"
                                      x-text="lv.status === 'approved' ? 'Approved' : 'Pending'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <div x-show="!dayLeaves().length" class="py-10 text-center text-sm text-slate-400">No one is on leave on this day.</div>
        </div>
    </div>

    {{-- ===================== SELECTED DAY PANEL (month/week) ===================== --}}
    <div x-show="selected && view !== 'day'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 dark:bg-slate-800 dark:border-slate-700/80">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white" x-text="selectedLabel"></h3>
            <div class="flex items-center gap-2">
                <a :href="'{{ route('time-off.create') }}?start_date=' + selected" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-slate-900 hover:bg-brand-700 transition">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> Request for this day
                </a>
                <button type="button" @click="selected = null" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
        </div>
        <template x-if="selectedLeaves().length">
            <div class="space-y-2">
                <template x-for="lv in selectedLeaves()" :key="lv.id">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 dark:border-slate-700/60">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="colorFor(lv.name).chip" x-text="lv.initials"></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold text-slate-900 dark:text-white truncate" x-text="lv.name"></div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="h-1.5 w-1.5 rounded-full" :class="colorFor(lv.name).dot"></span>
                                <span x-text="lv.policy"></span>
                                <span class="text-slate-300">·</span>
                                <span x-text="lv.duration"></span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-xs font-bold text-slate-700 dark:text-slate-300" x-text="rangeLabel(lv)"></div>
                            <span class="mt-0.5 inline-block rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                                  :class="lv.status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'"
                                  x-text="lv.status === 'approved' ? 'Approved' : 'Pending'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </template>
        <div x-show="!selectedLeaves().length" class="py-6 text-center text-sm text-slate-400">No one is on leave on this day.</div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-brand-500"></span> Day with leave</span>
        <span class="flex items-center gap-1.5"><span class="inline-block rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Approved</span></span>
        <span class="flex items-center gap-1.5"><span class="inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">Pending</span></span>
        <span class="text-slate-400">Each employee has their own colour.</span>
    </div>

    {{-- Tailwind safelist: keep palette classes generated by the Play CDN --}}
    <div class="hidden">
        <span class="bg-emerald-500 bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"></span>
        <span class="bg-sky-500 bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300"></span>
        <span class="bg-violet-500 bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300"></span>
        <span class="bg-amber-500 bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"></span>
        <span class="bg-rose-500 bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300"></span>
        <span class="bg-cyan-500 bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300"></span>
        <span class="bg-fuchsia-500 bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300"></span>
        <span class="bg-lime-500 bg-lime-100 text-lime-700 dark:bg-lime-500/20 dark:text-lime-300"></span>
    </div>
</div>

<script>
    function teamCalendar() {
        const PALETTE = [
            { dot: 'bg-emerald-500', chip: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' },
            { dot: 'bg-sky-500',     chip: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300' },
            { dot: 'bg-violet-500',  chip: 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300' },
            { dot: 'bg-amber-500',   chip: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' },
            { dot: 'bg-rose-500',    chip: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' },
            { dot: 'bg-cyan-500',    chip: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300' },
            { dot: 'bg-fuchsia-500', chip: 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300' },
            { dot: 'bg-lime-500',    chip: 'bg-lime-100 text-lime-700 dark:bg-lime-500/20 dark:text-lime-300' },
        ];

        const pad = n => String(n).padStart(2, '0');
        const toKey = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

        const today = new Date();
        const leaves = @js($leaves);

        // Year dropdown: a couple of years either side of now, plus any year a leave falls in.
        const yearSet = new Set([today.getFullYear() - 2, today.getFullYear() - 1, today.getFullYear(), today.getFullYear() + 1, today.getFullYear() + 2]);
        leaves.forEach(l => { yearSet.add(parseInt(l.start.slice(0, 4))); yearSet.add(parseInt(l.end.slice(0, 4))); });

        return {
            leaves,
            palette: PALETTE,
            view: 'month',
            cursor: new Date(today.getFullYear(), today.getMonth(), today.getDate()),
            year: today.getFullYear(),
            years: Array.from(yearSet).sort((a, b) => a - b),
            statusFilter: 'all',
            search: '',
            selected: null,
            todayKey: toKey(today),
            dayNames: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],

            get filtered() {
                const q = this.search.trim().toLowerCase();
                return this.leaves.filter(l => {
                    if (this.statusFilter !== 'all' && l.status !== this.statusFilter) return false;
                    if (q && !l.name.toLowerCase().includes(q)) return false;
                    return true;
                });
            },

            colorFor(name) {
                let h = 0;
                for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
                return this.palette[h % this.palette.length];
            },

            leavesOn(key) {
                return this.filtered.filter(l => l.start <= key && key <= l.end);
            },

            monthMatrix() {
                const y = this.cursor.getFullYear(), m = this.cursor.getMonth();
                const start = new Date(y, m, 1 - new Date(y, m, 1).getDay());
                const weeks = [];
                let d = start;
                for (let w = 0; w < 6; w++) {
                    const week = [];
                    for (let i = 0; i < 7; i++) {
                        const cur = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                        const key = toKey(cur);
                        week.push({
                            key, day: cur.getDate(),
                            inMonth: cur.getMonth() === m,
                            isToday: key === this.todayKey,
                            leaves: this.leavesOn(key),
                        });
                        d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
                    }
                    weeks.push(week);
                }
                return weeks;
            },

            weekStart() {
                const c = this.cursor;
                return new Date(c.getFullYear(), c.getMonth(), c.getDate() - c.getDay());
            },
            weekMatrix() {
                const ws = this.weekStart();
                const days = [];
                for (let i = 0; i < 7; i++) {
                    const cur = new Date(ws.getFullYear(), ws.getMonth(), ws.getDate() + i);
                    const key = toKey(cur);
                    days.push({
                        key, day: cur.getDate(),
                        name: cur.toLocaleDateString(undefined, { weekday: 'short' }),
                        isToday: key === this.todayKey,
                        leaves: this.leavesOn(key),
                    });
                }
                return days;
            },

            dayLeaves() { return this.leavesOn(toKey(this.cursor)); },

            selectedLeaves() { return this.selected ? this.leavesOn(this.selected) : []; },

            get selectedLabel() {
                if (!this.selected) return '';
                const [y, m, d] = this.selected.split('-').map(Number);
                return new Date(y, m - 1, d).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            },

            get title() {
                if (this.view === 'month') return this.cursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                if (this.view === 'day') return this.cursor.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                const ws = this.weekStart(), we = new Date(ws.getFullYear(), ws.getMonth(), ws.getDate() + 6);
                return ws.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' – ' + we.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            },

            rangeLabel(lv) {
                const fmt = k => { const [y, m, d] = k.split('-').map(Number); return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }); };
                return lv.start === lv.end ? fmt(lv.start) : fmt(lv.start) + ' – ' + fmt(lv.end);
            },

            shift(dir) {
                const c = this.cursor;
                if (this.view === 'month') this.cursor = new Date(c.getFullYear(), c.getMonth() + dir, 1);
                else if (this.view === 'week') this.cursor = new Date(c.getFullYear(), c.getMonth(), c.getDate() + 7 * dir);
                else this.cursor = new Date(c.getFullYear(), c.getMonth(), c.getDate() + dir);
                this.year = this.cursor.getFullYear();
                this.selected = null;
                this.$nextTick(() => window.lucide && window.lucide.createIcons());
            },
            goToday() {
                this.cursor = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                this.year = this.cursor.getFullYear();
                this.selected = this.todayKey;
            },
            setYear(y) {
                this.cursor = new Date(parseInt(y), this.cursor.getMonth(), this.view === 'month' ? 1 : this.cursor.getDate());
                this.selected = null;
            },
            selectDay(key) {
                this.selected = (this.selected === key) ? null : key;
                this.$nextTick(() => window.lucide && window.lucide.createIcons());
            },
        };
    }
</script>
@endsection
